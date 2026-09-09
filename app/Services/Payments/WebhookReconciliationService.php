<?php

namespace App\Services\Payments;

use App\Enums\PaymentGateway;
use App\Models\Payment;
use App\Models\PaymentWebhookLog;
use Illuminate\Support\Facades\DB;

class WebhookReconciliationService
{
    public function __construct(private readonly PaymentService $paymentService) {}

    public function handle(PaymentGateway $gateway, string $eventType, array $payload, ?string $signature, string $rawPayload): PaymentWebhookLog
    {
        $reference = $this->extractReference($gateway, $payload);
        $idempotencyKey = $this->extractIdempotencyKey($gateway, $payload, $eventType);

        if ($idempotencyKey && PaymentWebhookLog::query()
            ->where('gateway', $gateway->value)
            ->where('idempotency_key', $idempotencyKey)
            ->where('status', 'processed')
            ->exists()) {
            return PaymentWebhookLog::query()->create([
                'gateway' => $gateway->value,
                'event_type' => $eventType,
                'reference' => $reference,
                'idempotency_key' => $idempotencyKey,
                'payload' => $payload,
                'status' => 'duplicate',
                'processed_at' => now(),
            ]);
        }

        $gatewayDriver = $this->paymentService->resolveGateway($gateway);

        if (! $gatewayDriver->verifyWebhookSignature($rawPayload, $signature)) {
            return PaymentWebhookLog::query()->create([
                'gateway' => $gateway->value,
                'event_type' => $eventType,
                'reference' => $reference,
                'idempotency_key' => $idempotencyKey,
                'payload' => $payload,
                'status' => 'invalid_signature',
                'error_message' => 'Webhook signature verification failed.',
            ]);
        }

        $log = PaymentWebhookLog::query()->create([
            'gateway' => $gateway->value,
            'event_type' => $eventType,
            'reference' => $reference,
            'idempotency_key' => $idempotencyKey,
            'payload' => $payload,
            'status' => 'received',
        ]);

        try {
            DB::transaction(function () use ($gateway, $reference, $log) {
                $payment = Payment::query()->where('reference', $reference)->lockForUpdate()->first();

                if (! $payment) {
                    $log->update([
                        'status' => 'ignored',
                        'error_message' => 'Payment not found.',
                        'processed_at' => now(),
                    ]);

                    return;
                }

                $verification = $this->paymentService->resolveGateway($gateway)->verify($reference);

                if ($verification['status'] === 'successful') {
                    $this->paymentService->markSuccessful($payment, $verification);
                } else {
                    $this->paymentService->markFailed($payment, $verification);
                }

                $log->update(['status' => 'processed', 'processed_at' => now()]);
            });
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'processed_at' => now(),
            ]);
        }

        return $log->fresh();
    }

    private function extractReference(PaymentGateway $gateway, array $payload): ?string
    {
        return match ($gateway) {
            PaymentGateway::Paystack => $payload['data']['reference'] ?? $payload['reference'] ?? null,
            PaymentGateway::Flutterwave => $payload['data']['tx_ref'] ?? $payload['txRef'] ?? null,
        };
    }

    private function extractIdempotencyKey(PaymentGateway $gateway, array $payload, string $eventType): ?string
    {
        return match ($gateway) {
            PaymentGateway::Paystack => ($payload['data']['reference'] ?? '').':'.$eventType,
            PaymentGateway::Flutterwave => ($payload['data']['tx_ref'] ?? '').':'.$eventType,
        };
    }
}
