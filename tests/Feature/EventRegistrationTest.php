<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Models\Event;
use App\Notifications\TemplatedMailNotification;
use App\Models\EventRegistration;
use App\Models\Member;
use App\Models\MembershipTier;
use App\Models\User;
use Database\Seeders\MembershipTierSeeder;
use Database\Seeders\NotificationTemplateSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(NotificationTemplateSeeder::class);
        $this->seed(MembershipTierSeeder::class);
    }

    public function test_guest_can_register_for_free_event(): void
    {
        $admin = User::query()->where('email', 'admin@jocrams.test')->first();

        $event = Event::query()->create([
            'organizer_id' => $admin->id,
            'title' => 'Community Meetup',
            'slug' => 'community-meetup',
            'starts_at' => now()->addWeek(),
            'fee' => 0,
            'visibility' => 'public',
            'status' => 'published',
        ]);

        $response = $this->postJson("/api/v1/public/events/{$event->uuid}/register", [
            'name' => 'Guest Attendee',
            'email' => 'guest-attendee@gmail.com',
            'phone' => '+2348000000001',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('event_registrations', [
            'event_id' => $event->id,
            'registrant_email' => 'guest-attendee@gmail.com',
            'status' => 'confirmed',
        ]);
    }

    public function test_member_can_complete_paid_event_registration_via_paystack(): void
    {
        Http::fake([
            'api.paystack.co/*' => Http::sequence()
                ->push([
                    'status' => true,
                    'data' => [
                        'authorization_url' => 'https://checkout.paystack.com/test',
                        'access_code' => 'access_code',
                        'reference' => 'PAYSTACK_EVT1234567890',
                    ],
                ], 200)
                ->push([
                    'status' => true,
                    'data' => [
                        'status' => 'success',
                        'amount' => 500000,
                        'currency' => 'NGN',
                        'id' => '123456',
                        'paid_at' => now()->toIso8601String(),
                    ],
                ], 200),
        ]);

        $admin = User::query()->where('email', 'admin@jocrams.test')->first();
        $member = User::factory()->create(['email' => 'event-member@gmail.com']);

        $event = Event::query()->create([
            'organizer_id' => $admin->id,
            'title' => 'Paid Workshop',
            'slug' => 'paid-workshop',
            'starts_at' => now()->addWeeks(2),
            'fee' => 5000,
            'visibility' => 'public',
            'status' => 'published',
        ]);

        Sanctum::actingAs($member);

        $this->postJson("/api/v1/member/events/{$event->uuid}/register")
            ->assertStatus(422)
            ->assertJsonPath('requires_payment', true);

        $init = $this->postJson("/api/v1/member/events/{$event->uuid}/payments/initialize", [
            'gateway' => 'paystack',
            'idempotency_key' => 'event-reg-001',
        ])->assertCreated();

        $reference = $init->json('data.reference');

        $this->getJson("/api/v1/member/events/payments/verify/{$reference}")
            ->assertOk()
            ->assertJsonPath('data.status', 'successful');

        $this->assertDatabaseHas('event_registrations', [
            'event_id' => $event->id,
            'user_id' => $member->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_guest_member_ticket_requires_membership_number(): void
    {
        $admin = User::query()->where('email', 'admin@jocrams.test')->first();

        $event = Event::query()->create([
            'organizer_id' => $admin->id,
            'title' => 'Member Pricing Event',
            'slug' => 'member-pricing-event',
            'starts_at' => now()->addWeek(),
            'fee' => 0,
            'fees_by_category' => ['member' => 0, 'non_member' => 5000],
            'visibility' => 'public',
            'status' => 'published',
        ]);

        $this->postJson("/api/v1/public/events/{$event->uuid}/register", [
            'name' => 'Guest Member',
            'email' => 'guest-member@gmail.com',
            'member_type' => 'member',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['membership_number']);
    }

    public function test_guest_can_register_with_valid_membership_number(): void
    {
        $admin = User::query()->where('email', 'admin@jocrams.test')->first();
        $memberUser = User::factory()->create(['name' => 'Verified Member']);
        $tier = MembershipTier::query()->first();

        $member = Member::query()->create([
            'user_id' => $memberUser->id,
            'membership_tier_id' => $tier->id,
            'membership_number' => 'MEM-EVENT001',
            'status' => MemberStatus::Active,
            'joined_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $event = Event::query()->create([
            'organizer_id' => $admin->id,
            'title' => 'Member Ticket Event',
            'slug' => 'member-ticket-event',
            'starts_at' => now()->addWeek(),
            'fee' => 0,
            'fees_by_category' => ['member' => 0, 'non_member' => 5000],
            'visibility' => 'public',
            'status' => 'published',
        ]);

        $this->postJson("/api/v1/public/events/{$event->uuid}/register", [
            'name' => 'Verified Member',
            'email' => 'verified-member@gmail.com',
            'member_type' => 'member',
            'membership_number' => $member->membership_number,
        ])->assertCreated()
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('event_registrations', [
            'event_id' => $event->id,
            'member_id' => $member->id,
            'member_type' => 'member',
            'status' => 'confirmed',
        ]);
    }

    public function test_public_membership_number_verification_endpoint(): void
    {
        $memberUser = User::factory()->create(['name' => 'Jane Doe']);
        $tier = MembershipTier::query()->first();

        Member::query()->create([
            'user_id' => $memberUser->id,
            'membership_tier_id' => $tier->id,
            'membership_number' => 'MEM-VERIFY01',
            'status' => MemberStatus::Active,
            'joined_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $this->postJson('/api/v1/public/members/verify-number', [
            'membership_number' => 'MEM-VERIFY01',
        ])->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.member_name', 'Jane D.');

        $this->postJson('/api/v1/public/members/verify-number', [
            'membership_number' => 'MEM-NOTFOUND',
        ])->assertOk()
            ->assertJsonPath('data.valid', false);
    }

    public function test_guest_can_complete_paid_event_registration_via_paystack(): void
    {
        Http::fake([
            'api.paystack.co/*' => Http::sequence()
                ->push([
                    'status' => true,
                    'data' => [
                        'authorization_url' => 'https://checkout.paystack.com/test',
                        'access_code' => 'access_code',
                        'reference' => 'PAYSTACK_GUESTEVT001',
                    ],
                ], 200)
                ->push([
                    'status' => true,
                    'data' => [
                        'status' => 'success',
                        'amount' => 1000000,
                        'currency' => 'NGN',
                        'id' => '123456',
                        'paid_at' => now()->toIso8601String(),
                    ],
                ], 200),
        ]);

        $admin = User::query()->where('email', 'admin@jocrams.test')->first();
        $memberUser = User::factory()->create(['name' => 'Guest Checkout Member']);
        $tier = MembershipTier::query()->first();

        $member = Member::query()->create([
            'user_id' => $memberUser->id,
            'membership_tier_id' => $tier->id,
            'membership_number' => 'MEM-GUESTPAY1',
            'status' => MemberStatus::Active,
            'joined_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $event = Event::query()->create([
            'organizer_id' => $admin->id,
            'title' => 'Guest Paid Event',
            'slug' => 'guest-paid-event',
            'starts_at' => now()->addWeek(),
            'fee' => 15000,
            'fees_by_category' => ['member' => 10000, 'non_member' => 15000],
            'visibility' => 'public',
            'status' => 'published',
        ]);

        $init = $this->postJson("/api/v1/public/events/{$event->uuid}/payments/initialize", [
            'name' => 'Guest Checkout Member',
            'email' => 'guest-checkout@gmail.com',
            'gateway' => 'paystack',
            'idempotency_key' => 'guest-event-pay-001',
            'member_type' => 'member',
            'membership_number' => $member->membership_number,
        ])->assertCreated();

        $reference = $init->json('data.reference');

        $this->getJson("/api/v1/public/payments/verify/{$reference}")
            ->assertOk()
            ->assertJsonPath('data.status', 'successful')
            ->assertJsonPath('redirect_to', "/events/{$event->uuid}");

        $this->assertDatabaseHas('event_registrations', [
            'event_id' => $event->id,
            'member_id' => $member->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_guest_receives_event_confirmation_email_after_paid_registration(): void
    {
        Notification::fake();

        Http::fake([
            'api.paystack.co/*' => Http::sequence()
                ->push([
                    'status' => true,
                    'data' => [
                        'authorization_url' => 'https://checkout.paystack.com/test',
                        'access_code' => 'access_code',
                        'reference' => 'PAYSTACK_GUESTMAIL1',
                    ],
                ], 200)
                ->push([
                    'status' => true,
                    'data' => [
                        'status' => 'success',
                        'amount' => 1500000,
                        'currency' => 'NGN',
                        'id' => '123456',
                        'paid_at' => now()->toIso8601String(),
                    ],
                ], 200),
        ]);

        $admin = User::query()->where('email', 'admin@jocrams.test')->first();

        $event = Event::query()->create([
            'organizer_id' => $admin->id,
            'title' => 'Guest Email Event',
            'slug' => 'guest-email-event',
            'starts_at' => now()->addWeek(),
            'location' => 'Lagos, Nigeria',
            'fee' => 15000,
            'visibility' => 'public',
            'status' => 'published',
        ]);

        $this->postJson("/api/v1/public/events/{$event->uuid}/payments/initialize", [
            'name' => 'Guest Attendee',
            'email' => 'guest-nonmember@gmail.com',
            'gateway' => 'paystack',
            'idempotency_key' => 'guest-email-event-001',
            'member_type' => 'non_member',
        ])->assertCreated();

        $reference = \App\Models\Payment::query()->value('reference');

        $this->getJson("/api/v1/public/payments/verify/{$reference}")
            ->assertOk()
            ->assertJsonPath('registration.status', 'confirmed');

        Notification::assertSentOnDemand(
            TemplatedMailNotification::class,
            function ($notification, $channels, $notifiable): bool {
                return ($notifiable->routes['mail'] ?? null) === 'guest-nonmember@gmail.com';
            },
        );
    }

    public function test_member_can_view_their_event_registration(): void
    {
        $admin = User::query()->where('email', 'admin@jocrams.test')->first();
        $member = User::factory()->create(['email' => 'member-detail@gmail.com']);

        $event = Event::query()->create([
            'organizer_id' => $admin->id,
            'title' => 'Member Detail Event',
            'slug' => 'member-detail-event',
            'starts_at' => now()->addWeek(),
            'fee' => 0,
            'visibility' => 'public',
            'status' => 'published',
        ]);

        $registration = EventRegistration::query()->create([
            'event_id' => $event->id,
            'user_id' => $member->id,
            'registrant_name' => $member->name,
            'registrant_email' => $member->email,
            'member_type' => 'member',
            'status' => 'confirmed',
            'registration_number' => 'EVT-MEMBER01',
        ]);

        Sanctum::actingAs($member);

        $this->getJson("/api/v1/member/events/{$event->uuid}/registration")
            ->assertOk()
            ->assertJsonPath('data.uuid', $registration->uuid)
            ->assertJsonPath('data.status', 'confirmed');
    }

    public function test_admin_can_verify_pending_event_registration_payment(): void
    {
        Http::fake([
            'api.paystack.co/*' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'amount' => 1000000,
                    'currency' => 'NGN',
                    'id' => '123456',
                    'paid_at' => now()->toIso8601String(),
                ],
            ], 200),
        ]);

        $admin = User::query()->where('email', 'admin@jocrams.test')->first();
        $memberUser = User::factory()->create(['name' => 'Admin Verify Member']);
        $tier = MembershipTier::query()->first();

        $member = Member::query()->create([
            'user_id' => $memberUser->id,
            'membership_tier_id' => $tier->id,
            'membership_number' => 'MEM-ADMINVRF',
            'status' => MemberStatus::Active,
            'joined_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $event = Event::query()->create([
            'organizer_id' => $admin->id,
            'title' => 'Admin Verify Event',
            'slug' => 'admin-verify-event',
            'starts_at' => now()->addWeek(),
            'fee' => 10000,
            'visibility' => 'public',
            'status' => 'published',
        ]);

        $registration = EventRegistration::query()->create([
            'event_id' => $event->id,
            'member_id' => $member->id,
            'registrant_name' => 'Admin Verify Member',
            'registrant_email' => 'admin-verify@gmail.com',
            'member_type' => 'member',
            'status' => 'pending',
            'registration_number' => 'EVT-ADMIN001',
        ]);

        $payment = \App\Models\Payment::query()->create([
            'payable_type' => $registration->getMorphClass(),
            'payable_id' => $registration->id,
            'gateway' => 'paystack',
            'reference' => 'PAYSTACK_ADMINVERIFY1',
            'idempotency_key' => 'admin-verify-key',
            'amount' => 10000,
            'currency' => 'NGN',
            'status' => 'processing',
            'purpose' => 'event_fee',
            'metadata' => ['event_uuid' => $event->uuid],
        ]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/events/{$event->uuid}/registrations/{$registration->uuid}/verify-payment")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('event_registrations', [
            'id' => $registration->id,
            'status' => 'confirmed',
            'payment_id' => $payment->id,
        ]);
    }

    public function test_admin_can_list_and_export_event_registrations(): void
    {
        $admin = User::query()->where('email', 'admin@jocrams.test')->first();

        $event = Event::query()->create([
            'organizer_id' => $admin->id,
            'title' => 'Export Test Event',
            'slug' => 'export-test-event',
            'starts_at' => now()->addWeek(),
            'fee' => 0,
            'visibility' => 'public',
            'status' => 'published',
        ]);

        EventRegistration::query()->create([
            'event_id' => $event->id,
            'registrant_name' => 'Export User',
            'registrant_email' => 'export-user@gmail.com',
            'member_type' => 'non_member',
            'status' => 'confirmed',
            'registration_number' => 'EVT-TEST1234',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson("/api/v1/admin/events/{$event->uuid}/registrations")
            ->assertOk()
            ->assertJsonPath('stats.confirmed', 1)
            ->assertJsonPath('data.data.0.registrant_email', 'export-user@gmail.com');

        $this->get("/api/v1/admin/events/{$event->uuid}/registrations/export")
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
