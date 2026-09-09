<?php

namespace App\Services\Payments\Gateways;

use App\Models\Payment;
use App\Services\Payments\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;

class PaystackGateway implements PaymentGatewayInterface
{
    public function initialize(Payment $payment, array $customer): array
    {
        $callbackUrl = $customer['callback_url'] ?? config('payments.callback_url');

        $response = Http::withToken(config('payments.paystack.secret_key'))
            ->post(config('payments.paystack.base_url').'/transaction/initialize', [
                'email' => $customer['email'],
                'amount' => (int) round($payment->amount * 100),
                'currency' => $payment->currency,
                'reference' => $payment->reference,
                'callback_url' => $callbackUrl,
                'metadata' => array_merge($payment->metadata ?? [], [
                    'payment_uuid' => $payment->uuid,
                    'purpose' => $payment->purpose->value,
                ]),
            ])
            ->throw()
            ->json('data');

        return [
            'authorization_url' => $response['authorization_url'],
            'access_code' => $response['access_code'],
            'reference' => $response['reference'],
        ];
    }

    public function verify(string $reference): array
    {
        $response = Http::withToken(config('payments.paystack.secret_key'))
            ->get(config('payments.paystack.base_url')."/transaction/verify/{$reference}")
            ->throw()
            ->json('data');

        return [
            'status' => $response['status'] === 'success' ? 'successful' : 'failed',
            'amount' => $response['amount'] / 100,
            'currency' => $response['currency'],
            'gateway_reference' => (string) $response['id'],
            'paid_at' => $response['paid_at'] ?? null,
            'raw' => $response,
        ];
    }

    public function verifyWebhookSignature(string $payload, ?string $signature): bool
    {
        $secret = config('payments.paystack.webhook_secret');

        if (! $secret || ! $signature) {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $payload, $secret), $signature);
    }
}
