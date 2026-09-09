<?php

namespace App\Services\Payments\Contracts;

use App\Models\Payment;

interface PaymentGatewayInterface
{
    public function initialize(Payment $payment, array $customer): array;

    public function verify(string $reference): array;

    public function verifyWebhookSignature(string $payload, ?string $signature): bool;
}
