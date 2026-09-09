<?php

namespace App\Services\Admin;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\PaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentOverrideService
{
    public function __construct(
        private readonly ActivityLogService $activityLog,
        private readonly PaymentService $paymentService,
    ) {}

    public function markSuccessful(Payment $payment, User $admin, ?string $reason = null): Payment
    {
        if ($payment->isSuccessful()) {
            throw ValidationException::withMessages(['payment' => ['Payment is already successful.']]);
        }

        return DB::transaction(function () use ($payment, $admin, $reason) {
            $payment->update([
                'metadata' => array_merge($payment->metadata ?? [], [
                    'manual_override' => true,
                    'override_by' => $admin->id,
                    'override_reason' => $reason,
                    'overridden_at' => now()->toIso8601String(),
                ]),
            ]);

            $updated = $this->paymentService->markSuccessful($payment, [
                'status' => 'successful',
                'amount' => (float) $payment->amount,
                'currency' => $payment->currency,
                'gateway_reference' => 'MANUAL-'.$payment->reference,
                'paid_at' => now()->toIso8601String(),
                'raw' => ['manual_override' => true, 'admin_id' => $admin->id],
            ]);

            $this->activityLog->log($admin, 'payments.manual_override', $updated, [
                'reason' => $reason,
                'reference' => $payment->reference,
            ]);

            return $updated;
        });
    }

    public function markFailed(Payment $payment, User $admin, string $reason): Payment
    {
        if ($payment->status === PaymentStatus::Successful) {
            throw ValidationException::withMessages(['payment' => ['Cannot fail a successful payment.']]);
        }

        $payment->update([
            'status' => PaymentStatus::Failed,
            'metadata' => array_merge($payment->metadata ?? [], [
                'manual_override' => true,
                'override_by' => $admin->id,
                'override_reason' => $reason,
            ]),
        ]);

        $this->activityLog->log($admin, 'payments.manual_fail', $payment, ['reason' => $reason]);

        return $payment->fresh();
    }
}
