<?php

namespace App\Services\Admin;

use App\Enums\PaymentGateway;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Models\Donation;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payments\PaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentAdminService
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function recordManual(array $data, User $admin): Payment
    {
        return DB::transaction(function () use ($data, $admin) {
            $member = Member::query()->where('uuid', $data['member_uuid'])->with('user', 'tier')->firstOrFail();
            $user = $member->user;

            if (! $user) {
                throw ValidationException::withMessages([
                    'member_uuid' => ['Member has no linked user account.'],
                ]);
            }

            $purpose = PaymentPurpose::from($data['purpose']);
            $payable = $this->resolvePayable($member, $purpose, (float) $data['amount']);

            $payment = Payment::query()->create([
                'user_id' => $user->id,
                'member_id' => $member->id,
                'payable_type' => $payable?->getMorphClass(),
                'payable_id' => $payable?->getKey(),
                'gateway' => PaymentGateway::Manual,
                'reference' => $data['transaction_reference'],
                'idempotency_key' => 'manual_'.(string) Str::uuid(),
                'amount' => $data['amount'],
                'currency' => config('payments.currency', 'NGN'),
                'status' => PaymentStatus::Pending,
                'purpose' => $purpose,
                'description' => $data['description'] ?? "Manual {$purpose->value} payment",
                'metadata' => [
                    'payment_method' => $data['payment_method'],
                    'recorded_by' => $admin->id,
                    'manual_record' => true,
                ],
            ]);

            $updated = $this->paymentService->markSuccessful($payment, [
                'status' => 'successful',
                'amount' => (float) $data['amount'],
                'currency' => $payment->currency,
                'gateway_reference' => $data['transaction_reference'],
                'paid_at' => now()->toIso8601String(),
                'raw' => [
                    'manual_record' => true,
                    'payment_method' => $data['payment_method'],
                    'admin_id' => $admin->id,
                ],
            ]);

            $this->activityLog->log($admin, 'payments.recorded', $updated, [
                'reference' => $updated->reference,
                'amount' => (string) $updated->amount,
                'purpose' => $updated->purpose->value,
            ]);

            return $updated->fresh(['user', 'member']);
        });
    }

    /** @return array<string, mixed> */
    public function stats(): array
    {
        return [
            'total_revenue' => (float) Payment::query()
                ->where('status', PaymentStatus::Successful)
                ->sum('amount'),
            'pending_count' => Payment::query()
                ->whereIn('status', [PaymentStatus::Pending, PaymentStatus::Processing])
                ->count(),
            'successful_count' => Payment::query()
                ->where('status', PaymentStatus::Successful)
                ->count(),
            'failed_count' => Payment::query()
                ->where('status', PaymentStatus::Failed)
                ->count(),
        ];
    }

    private function resolvePayable(Member $member, PaymentPurpose $purpose, float $amount): ?object
    {
        return match ($purpose) {
            PaymentPurpose::Dues => Subscription::query()->create([
                'member_id' => $member->id,
                'membership_tier_id' => $member->membership_tier_id,
                'starts_at' => now(),
                'ends_at' => now()->addYear(),
                'status' => 'pending',
                'amount' => $amount,
                'currency' => $member->tier->currency,
            ]),
            PaymentPurpose::Donation => Donation::query()->create([
                'user_id' => $member->user_id,
                'amount' => $amount,
                'currency' => config('payments.currency', 'NGN'),
                'donor_name' => $member->user?->name ?? 'Member',
                'donor_email' => $member->user?->email ?? '',
            ]),
            default => null,
        };
    }
}
