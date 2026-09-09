<?php

namespace App\Services\Payments\Gateways;

use App\Models\Payment;
use App\Services\Payments\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;

class FlutterwaveGateway implements PaymentGatewayInterface
{
    public function initialize(Payment $payment, array $customer): array
    {
        $response = Http::withToken(config('payments.flutterwave.secret_key'))
            ->post(config('payments.flutterwave.base_url').'/payments', [
                'tx_ref' => $payment->reference,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'redirect_url' => $customer['callback_url'] ?? config('payments.callback_url'),
                'customer' => [
                    'email' => $customer['email'],
                    'name' => $customer['name'] ?? $customer['email'],
                    'phonenumber' => $customer['phone'] ?? '',
                ],
                'customizations' => [
                    'title' => config('app.name'),
                    'description' => $payment->description ?? $payment->purpose->value,
                ],
                'meta' => array_merge($payment->metadata ?? [], [
                    'payment_uuid' => $payment->uuid,
                    'purpose' => $payment->purpose->value,
                ]),
            ])
            ->throw()
            ->json('data');

        return [
            'authorization_url' => $response['link'],
            'access_code' => $response['id'] ?? null,
            'reference' => $payment->reference,
        ];
    }

    public function verify(string $reference): array
    {
        $response = Http::withToken(config('payments.flutterwave.secret_key'))
            ->get(config('payments.flutterwave.base_url')."/transactions/verify_by_reference?tx_ref={$reference}")
            ->throw()
            ->json('data');

        return [
            'status' => $response['status'] === 'successful' ? 'successful' : 'failed',
            'amount' => (float) $response['amount'],
            'currency' => $response['currency'],
            'gateway_reference' => (string) ($response['id'] ?? $reference),
            'paid_at' => $response['created_at'] ?? null,
            'raw' => $response,
        ];
    }

    public function verifyWebhookSignature(string $payload, ?string $signature): bool
    {
        $secret = config('payments.flutterwave.webhook_secret');

        if (! $secret || ! $signature) {
            return false;
        }

        return hash_equals($secret, $signature);
    }
}
