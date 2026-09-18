<?php

namespace App\Services\Events;

use App\Enums\PaymentGateway;
use App\Enums\PaymentPurpose;
use App\Enums\Visibility;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Member;
use App\Models\Payment;
use App\Models\User;
use App\Services\Communications\NotificationDispatchService;
use App\Services\Payments\PaymentService;
use App\Support\Events\EventRegistrationNumberGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventRegistrationService
{
    public function __construct(
        private readonly EventFeeResolver $feeResolver,
        private readonly EventRegistrationMemberTypeResolver $memberTypeResolver,
        private readonly EventMembershipVerifier $membershipVerifier,
        private readonly PaymentService $paymentService,
    ) {}

    /** @param array<string, mixed> $data */
    public function registerMember(User $user, Event $event, array $data): EventRegistration
    {
        $this->assertEventAvailable($event, $user);

        $memberType = $this->memberTypeResolver->resolve($user, $data['member_type'] ?? null);
        $fee = $this->feeResolver->resolveFee($event, $memberType);

        if ($fee > 0) {
            throw ValidationException::withMessages([
                'payment' => ['Payment required. Initialize payment to complete registration.'],
                'fee' => [$fee],
                'member_type' => [$memberType],
            ]);
        }

        return $this->createConfirmedRegistration(
            event: $event,
            name: $data['name'] ?? $user->name,
            email: $data['email'] ?? $user->email,
            phone: $data['phone'] ?? $user->phone,
            memberType: $memberType,
            user: $user,
        );
    }

    /** @param array<string, mixed> $data */
    public function registerGuest(Event $event, array $data): EventRegistration
    {
        $this->assertEventAvailable($event);

        if ($event->visibility === Visibility::MembersOnly) {
            throw ValidationException::withMessages([
                'event' => ['This event is restricted to members. Please sign in to register.'],
            ]);
        }

        $memberType = $this->memberTypeResolver->resolve(null, $data['member_type'] ?? null);
        $member = $this->resolveMemberForGuestTicket($memberType, $data['membership_number'] ?? null);
        $fee = $this->feeResolver->resolveFee($event, $memberType);

        if ($fee > 0) {
            throw ValidationException::withMessages([
                'payment' => ['Payment required. Initialize payment to complete registration.'],
                'fee' => [$fee],
                'member_type' => [$memberType],
            ]);
        }

        return $this->createConfirmedRegistration(
            event: $event,
            name: $data['name'],
            email: $data['email'],
            phone: $data['phone'] ?? null,
            memberType: $memberType,
            member: $member,
        );
    }

    /**
     * Reserve a pending registration for bank-transfer payment (no gateway checkout).
     *
     * @return array{registration: EventRegistration, fee: float, currency: string}
     */
    public function beginMemberBankTransfer(
        User $user,
        Event $event,
        ?string $memberType = null,
        ?string $name = null,
        ?string $email = null,
        ?string $phone = null,
    ): array {
        $this->assertEventAvailable($event, $user);

        $memberType = $this->memberTypeResolver->resolve($user, $memberType);
        $fee = $this->feeResolver->resolveFee($event, $memberType);

        if ($fee <= 0) {
            throw ValidationException::withMessages([
                'payment' => ['This event does not require payment. Register directly instead.'],
            ]);
        }

        $registration = $this->findOrCreatePendingRegistration(
            event: $event,
            name: $name ?? $user->name,
            email: $email ?? $user->email,
            phone: $phone ?? $user->phone,
            memberType: $memberType,
            user: $user,
        )->load('event');

        return [
            'registration' => $registration,
            'fee' => $fee,
            'currency' => config('payments.currency', 'NGN'),
        ];
    }

    /**
     * @return array{registration: EventRegistration, fee: float, currency: string}
     */
    public function beginGuestBankTransfer(
        Event $event,
        string $name,
        string $email,
        ?string $phone = null,
        ?string $memberType = null,
        ?string $membershipNumber = null,
    ): array {
        $this->assertEventAvailable($event);

        if ($event->visibility === Visibility::MembersOnly) {
            throw ValidationException::withMessages([
                'event' => ['This event is restricted to members. Please sign in to register.'],
            ]);
        }

        $memberType = $this->memberTypeResolver->resolve(null, $memberType);
        $member = $this->resolveMemberForGuestTicket($memberType, $membershipNumber);
        $fee = $this->feeResolver->resolveFee($event, $memberType);

        if ($fee <= 0) {
            throw ValidationException::withMessages([
                'payment' => ['This event does not require payment. Register directly instead.'],
            ]);
        }

        $registration = $this->findOrCreatePendingRegistration(
            event: $event,
            name: $name,
            email: $email,
            phone: $phone,
            memberType: $memberType,
            member: $member,
        )->load('event');

        return [
            'registration' => $registration,
            'fee' => $fee,
            'currency' => config('payments.currency', 'NGN'),
        ];
    }

    public function initiateMemberPayment(
        User $user,
        Event $event,
        PaymentGateway $gateway,
        string $idempotencyKey,
        ?string $memberType = null,
        ?string $name = null,
        ?string $email = null,
        ?string $phone = null,
    ): Payment {
        if ($gateway === PaymentGateway::Manual) {
            throw ValidationException::withMessages([
                'gateway' => ['Use the bank transfer registration flow instead of a payment gateway.'],
            ]);
        }

        $result = $this->beginMemberBankTransfer($user, $event, $memberType, $name, $email, $phone);

        return $this->paymentService->initiateEventRegistration(
            user: $user,
            registration: $result['registration'],
            amount: $result['fee'],
            gateway: $gateway,
            idempotencyKey: $idempotencyKey,
            memberType: $memberType ?? $result['registration']->member_type,
        );
    }

    public function initiateGuestPayment(
        Event $event,
        PaymentGateway $gateway,
        string $idempotencyKey,
        string $name,
        string $email,
        ?string $phone = null,
        ?string $memberType = null,
        ?string $membershipNumber = null,
    ): Payment {
        if ($gateway === PaymentGateway::Manual) {
            throw ValidationException::withMessages([
                'gateway' => ['Use the bank transfer registration flow instead of a payment gateway.'],
            ]);
        }

        $result = $this->beginGuestBankTransfer($event, $name, $email, $phone, $memberType, $membershipNumber);

        return $this->paymentService->initiateGuestPayment(
            email: $email,
            name: $name,
            amount: $result['fee'],
            gateway: $gateway,
            idempotencyKey: $idempotencyKey,
            payable: $result['registration'],
            phone: $phone,
            description: "Event registration - {$event->title}",
            metadata: [
                'event_uuid' => $event->uuid,
                'member_type' => $result['registration']->member_type,
                'membership_number' => $membershipNumber,
            ],
        );
    }

    public function verifyPayment(string $reference): Payment
    {
        $payment = Payment::query()->where('reference', $reference)->firstOrFail();

        return $this->paymentService->verify($payment);
    }

    public function notifyConfirmation(EventRegistration $registration): void
    {
        $registration->loadMissing('event', 'user', 'member.user');

        $variables = [
            'name' => $registration->registrant_name,
            'event_title' => $registration->event->title,
            'event_date' => $registration->event->starts_at?->toDayDateTimeString() ?? '',
            'event_location' => $registration->event->location ?? 'TBA',
            'registration_number' => $registration->registration_number,
            'app_name' => config('app.name'),
        ];

        $recipient = $registration->user ?? $registration->member?->user;

        if ($recipient) {
            app(NotificationDispatchService::class)->sendBySlug('event-confirmation', $recipient, $variables, $registration);

            return;
        }

        if ($registration->registrant_email) {
            app(NotificationDispatchService::class)->sendBySlugToEmail(
                'event-confirmation',
                $registration->registrant_email,
                $variables,
                $registration,
            );
        }
    }

    private function assertEventAvailable(Event $event, ?User $user = null): void
    {
        if ($event->status !== 'published') {
            throw ValidationException::withMessages(['event' => ['Event is not open for registration.']]);
        }

        if ($event->visibility === Visibility::MembersOnly) {
            if (! $user || ! $user->member?->isActive()) {
                throw ValidationException::withMessages(['event' => ['This event is restricted to active members.']]);
            }
        }

        if ($event->registration_opens_at && now()->lt($event->registration_opens_at)) {
            throw ValidationException::withMessages(['event' => ['Registration has not opened yet.']]);
        }

        if ($event->registration_closes_at && now()->gt($event->registration_closes_at)) {
            throw ValidationException::withMessages(['event' => ['Registration has closed.']]);
        }

        $confirmedCount = $event->registrations()->where('status', 'confirmed')->count();
        if ($event->max_attendees && $confirmedCount >= $event->max_attendees) {
            throw ValidationException::withMessages(['event' => ['Event is full.']]);
        }
    }

    private function findOrCreatePendingRegistration(
        Event $event,
        string $name,
        string $email,
        ?string $phone,
        string $memberType,
        ?User $user = null,
        ?Member $member = null,
    ): EventRegistration {
        $member ??= $user?->member;
        $existing = $this->findExistingRegistration($event, $user, $email);

        if ($existing) {
            if ($existing->status === 'confirmed') {
                throw ValidationException::withMessages(['registration' => ['You are already registered for this event.']]);
            }

            $existing->update([
                'registrant_name' => $name,
                'registrant_email' => $email,
                'registrant_phone' => $phone,
                'member_type' => $memberType,
                'member_id' => $member?->id,
                'user_id' => $user?->id ?? $member?->user_id,
            ]);

            return $existing->fresh();
        }

        return EventRegistration::query()->create([
            'event_id' => $event->id,
            'user_id' => $user?->id ?? $member?->user_id,
            'member_id' => $member?->id,
            'registrant_name' => $name,
            'registrant_email' => $email,
            'registrant_phone' => $phone,
            'member_type' => $memberType,
            'status' => 'pending',
            'registration_number' => EventRegistrationNumberGenerator::generate(),
            'metadata' => [
                'member_type' => $memberType,
                'membership_number' => $member?->membership_number,
            ],
        ]);
    }

    private function createConfirmedRegistration(
        Event $event,
        string $name,
        string $email,
        ?string $phone,
        string $memberType,
        ?User $user = null,
        ?Member $member = null,
    ): EventRegistration {
        $member ??= $user?->member;
        $existing = $this->findExistingRegistration($event, $user, $email);

        if ($existing?->status === 'confirmed') {
            throw ValidationException::withMessages(['registration' => ['You are already registered for this event.']]);
        }

        if ($existing) {
            $existing->update([
                'registrant_name' => $name,
                'registrant_email' => $email,
                'registrant_phone' => $phone,
                'member_type' => $memberType,
                'member_id' => $member?->id,
                'user_id' => $user?->id ?? $member?->user_id,
                'status' => 'confirmed',
            ]);
            $registration = $existing;
        } else {
            $registration = EventRegistration::query()->create([
                'event_id' => $event->id,
                'user_id' => $user?->id ?? $member?->user_id,
                'member_id' => $member?->id,
                'registrant_name' => $name,
                'registrant_email' => $email,
                'registrant_phone' => $phone,
                'member_type' => $memberType,
                'status' => 'confirmed',
                'registration_number' => EventRegistrationNumberGenerator::generate(),
                'metadata' => [
                    'member_type' => $memberType,
                    'membership_number' => $member?->membership_number,
                ],
            ]);
        }

        $this->notifyConfirmation($registration);

        return $registration->fresh(['event']);
    }

    private function resolveMemberForGuestTicket(string $memberType, ?string $membershipNumber): ?Member
    {
        if (! $this->membershipVerifier->requiresMembershipNumber($memberType)) {
            return null;
        }

        return $this->membershipVerifier->assertActiveMemberTicket($membershipNumber);
    }

    private function findExistingRegistration(Event $event, ?User $user, ?string $email): ?EventRegistration
    {
        $query = EventRegistration::query()->where('event_id', $event->id);

        if ($user) {
            return $query->where('user_id', $user->id)->first();
        }

        if ($email) {
            return $query->where('registrant_email', $email)->first();
        }

        return null;
    }
}
