<?php

namespace App\Services\Payments;

use App\Enums\MemberStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Models\Donation;
use App\Models\EventRegistration;
use App\Models\JournalSubmission;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Membership\MembershipRenewalService;
use App\Services\Payments\PaymentReceiptDeliveryService;
use App\Services\Payments\Contracts\PaymentGatewayInterface;
use App\Services\Payments\Gateways\FlutterwaveGateway;
use App\Services\Payments\Gateways\PaystackGateway;
use App\Support\Payments\PaymentReturnResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function resolveGateway(PaymentGateway|string $gateway): PaymentGatewayInterface
    {
        $gateway = $gateway instanceof PaymentGateway ? $gateway : PaymentGateway::from($gateway);

        return match ($gateway) {
            PaymentGateway::Paystack => app(PaystackGateway::class),
            PaymentGateway::Flutterwave => app(FlutterwaveGateway::class),
        };
    }

    public function initiate(
        User $user,
        float $amount,
        PaymentPurpose $purpose,
        PaymentGateway $gateway,
        string $idempotencyKey,
        ?Model $payable = null,
        ?Member $member = null,
        ?string $description = null,
        array $metadata = [],
    ): Payment {
        $existing = Payment::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing) {
            return $existing;
        }

        $reference = $this->generateReference($gateway);

        $payment = Payment::query()->create([
            'user_id' => $user->id,
            'member_id' => $member?->id ?? $user->member?->id,
            'payable_type' => $payable ? $payable->getMorphClass() : null,
            'payable_id' => $payable?->getKey(),
            'gateway' => $gateway,
            'reference' => $reference,
            'idempotency_key' => $idempotencyKey,
            'amount' => $amount,
            'currency' => config('payments.currency', 'NGN'),
            'status' => PaymentStatus::Pending,
            'purpose' => $purpose,
            'description' => $description,
            'metadata' => $metadata,
            'expires_at' => now()->addHours(24),
        ]);

        $gatewayDriver = $this->resolveGateway($gateway);
        $init = $gatewayDriver->initialize($payment, [
            'email' => $user->email,
            'name' => $user->name,
            'phone' => $user->phone,
            'callback_url' => app(PaymentReturnResolver::class)->callbackUrl($payment),
        ]);

        $payment->update([
            'authorization_url' => $init['authorization_url'],
            'status' => PaymentStatus::Processing,
        ]);

        return $payment->fresh();
    }

    public function initiateDuesPayment(User $user, string $idempotencyKey, PaymentGateway $gateway): Payment
    {
        $member = $user->member;

        if (! $member) {
            throw ValidationException::withMessages(['member' => ['Active membership required.']]);
        }

        app(MembershipRenewalService::class)->assertCanRenew($member);

        $subscription = Subscription::query()->create([
            'member_id' => $member->id,
            'membership_tier_id' => $member->membership_tier_id,
            'starts_at' => now(),
            'ends_at' => now()->addYear(),
            'status' => 'pending',
            'amount' => $member->tier->annual_dues,
            'currency' => $member->tier->currency,
        ]);

        return $this->initiate(
            user: $user,
            amount: (float) $member->tier->annual_dues,
            purpose: PaymentPurpose::Dues,
            gateway: $gateway,
            idempotencyKey: $idempotencyKey,
            payable: $subscription,
            member: $member,
            description: "Annual dues - {$member->tier->name}",
        );
    }

    public function initiateDonation(
        User $user,
        float $amount,
        string $idempotencyKey,
        PaymentGateway $gateway,
        string $donorName,
        string $donorEmail,
        bool $isAnonymous = false,
        ?string $message = null,
    ): Payment {
        $donation = Donation::query()->create([
            'user_id' => $user->id,
            'amount' => $amount,
            'currency' => config('payments.currency', 'NGN'),
            'donor_name' => $donorName,
            'donor_email' => $donorEmail,
            'is_anonymous' => $isAnonymous,
            'message' => $message,
        ]);

        return $this->initiate(
            user: $user,
            amount: $amount,
            purpose: PaymentPurpose::Donation,
            gateway: $gateway,
            idempotencyKey: $idempotencyKey,
            payable: $donation,
            description: 'Donation',
        );
    }

    public function initiateEventRegistration(
        User $user,
        EventRegistration $registration,
        float $amount,
        PaymentGateway $gateway,
        string $idempotencyKey,
        string $memberType,
    ): Payment {
        return $this->initiate(
            user: $user,
            amount: $amount,
            purpose: PaymentPurpose::EventFee,
            gateway: $gateway,
            idempotencyKey: $idempotencyKey,
            payable: $registration,
            member: $user->member,
            description: "Event registration - {$registration->event?->title}",
            metadata: [
                'event_uuid' => $registration->event?->uuid,
                'member_type' => $memberType,
                'registration_uuid' => $registration->uuid,
            ],
        );
    }

    public function initiateJournalSubmissionFee(
        User $user,
        JournalSubmission $submission,
        PaymentGateway $gateway,
        string $idempotencyKey,
    ): Payment {
        $amount = (float) ($submission->submission_fee_amount ?? 0);

        if ($amount <= 0) {
            throw ValidationException::withMessages(['payment' => ['No submission fee is required for this manuscript.']]);
        }

        return $this->initiate(
            user: $user,
            amount: $amount,
            purpose: PaymentPurpose::JournalSubmissionFee,
            gateway: $gateway,
            idempotencyKey: $idempotencyKey,
            payable: $submission,
            member: $user->member,
            description: "Journal submission fee - {$submission->title}",
            metadata: [
                'submission_uuid' => $submission->uuid,
                'call_for_papers_id' => $submission->call_for_papers_id,
            ],
        );
    }

    public function initiateGuestPayment(
        string $email,
        string $name,
        float $amount,
        PaymentGateway $gateway,
        string $idempotencyKey,
        Model $payable,
        ?string $phone = null,
        ?string $description = null,
        array $metadata = [],
    ): Payment {
        $existing = Payment::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing) {
            return $existing;
        }

        $reference = $this->generateReference($gateway);

        $payment = Payment::query()->create([
            'user_id' => null,
            'member_id' => $payable instanceof EventRegistration ? $payable->member_id : null,
            'payable_type' => $payable->getMorphClass(),
            'payable_id' => $payable->getKey(),
            'gateway' => $gateway,
            'reference' => $reference,
            'idempotency_key' => $idempotencyKey,
            'amount' => $amount,
            'currency' => config('payments.currency', 'NGN'),
            'status' => PaymentStatus::Pending,
            'purpose' => PaymentPurpose::EventFee,
            'description' => $description,
            'metadata' => array_merge($metadata, [
                'guest_name' => $name,
                'guest_email' => $email,
                'guest_phone' => $phone,
            ]),
            'expires_at' => now()->addHours(24),
        ]);

        $gatewayDriver = $this->resolveGateway($gateway);
        $init = $gatewayDriver->initialize($payment, [
            'email' => $email,
            'name' => $name,
            'phone' => $phone,
            'callback_url' => app(PaymentReturnResolver::class)->callbackUrl($payment),
        ]);

        $payment->update([
            'authorization_url' => $init['authorization_url'],
            'status' => PaymentStatus::Processing,
        ]);

        return $payment->fresh();
    }

    public function verify(Payment $payment): Payment
    {
        $gatewayDriver = $this->resolveGateway($payment->gateway);
        $verification = $gatewayDriver->verify($payment->reference);

        if ($verification['status'] === 'successful') {
            return $this->markSuccessful($payment, $verification);
        }

        return $this->markFailed($payment, $verification);
    }

    public function markSuccessful(Payment $payment, array $verification): Payment
    {
        if ($payment->isSuccessful()) {
            return $payment;
        }

        return DB::transaction(function () use ($payment, $verification) {
            $payment->update([
                'status' => PaymentStatus::Successful,
                'paid_at' => $verification['paid_at'] ? now()->parse($verification['paid_at']) : now(),
                'metadata' => array_merge($payment->metadata ?? [], ['verification' => $verification['raw'] ?? []]),
            ]);

            $payment->transactions()->create([
                'gateway_reference' => $verification['gateway_reference'],
                'type' => 'charge',
                'amount' => $verification['amount'],
                'currency' => $verification['currency'],
                'status' => 'successful',
                'gateway_response' => $verification['raw'] ?? [],
                'processed_at' => now(),
            ]);

            $this->fulfillPayable($payment);

            app(PaymentReceiptDeliveryService::class)->send($payment->fresh(['user', 'member.tier', 'payable']));

            return $payment->fresh();
        });
    }

    public function markFailed(Payment $payment, array $verification): Payment
    {
        $payment->update(['status' => PaymentStatus::Failed]);

        $payment->transactions()->create([
            'gateway_reference' => $verification['gateway_reference'] ?? null,
            'type' => 'charge',
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'status' => 'failed',
            'gateway_response' => $verification['raw'] ?? [],
            'processed_at' => now(),
        ]);

        return $payment->fresh();
    }

    private function fulfillPayable(Payment $payment): void
    {
        $payable = $payment->payable;

        if ($payable instanceof Subscription) {
            $payable->update([
                'payment_id' => $payment->id,
                'status' => 'active',
            ]);

            $payable->member->update([
                'expires_at' => $payable->ends_at,
                'status' => MemberStatus::Active,
            ]);
        }

        if ($payable instanceof Donation) {
            $payable->update(['payment_id' => $payment->id]);
        }

        if ($payable instanceof EventRegistration) {
            $payable->update([
                'payment_id' => $payment->id,
                'status' => 'confirmed',
            ]);

            app(\App\Services\Events\EventRegistrationService::class)->notifyConfirmation($payable->fresh(['event', 'user']));
        }

        if ($payable instanceof JournalSubmission) {
            $payable->update(['submission_payment_id' => $payment->id]);
            app(\App\Services\Journal\JournalSubmissionService::class)->finalizePaidSubmission($payable->fresh());
        }
    }

    private function generateReference(PaymentGateway $gateway): string
    {
        do {
            $reference = strtoupper($gateway->value).'_'.Str::upper(Str::random(16));
        } while (Payment::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
