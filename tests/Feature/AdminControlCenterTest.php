<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\MemberStatus;
use App\Enums\PaymentStatus;
use App\Models\Member;
use App\Models\MembershipApplication;
use App\Models\MembershipTier;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\TemplatedMailNotification;
use Database\Seeders\MembershipTierSeeder;
use Database\Seeders\NotificationTemplateSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminControlCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(MembershipTierSeeder::class);
        $this->seed(NotificationTemplateSeeder::class);
        Storage::fake('local');
    }

    public function test_admin_dashboard_returns_stats(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@jocrams.test')->first());

        $this->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonStructure(['stats' => ['members', 'applications', 'approvals', 'payments']]);
    }

    public function test_admin_can_filter_members(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@jocrams.test')->first());

        $user = User::factory()->create();
        $tier = MembershipTier::query()->first();
        Member::query()->create([
            'user_id' => $user->id,
            'membership_tier_id' => $tier->id,
            'membership_number' => 'MEM-FILTER01',
            'status' => MemberStatus::Active,
            'joined_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $this->getJson('/api/v1/admin/members?search=MEM-FILTER01')
            ->assertOk()
            ->assertJsonPath('data.0.membership_number', 'MEM-FILTER01');
    }

    public function test_admin_can_export_members_csv(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@jocrams.test')->first());

        $response = $this->get('/api/v1/admin/exports/members?format=csv');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_admin_can_override_payment_status(): void
    {
        Notification::fake();

        $admin = User::query()->where('email', 'admin@jocrams.test')->first();
        $user = User::factory()->create();

        $payment = Payment::query()->create([
            'user_id' => $user->id,
            'gateway' => 'paystack',
            'reference' => 'PAYSTACK_OVERRIDE001',
            'idempotency_key' => 'override-key-001',
            'amount' => 5000,
            'currency' => 'NGN',
            'status' => PaymentStatus::Pending,
            'purpose' => 'dues',
        ]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/payments/{$payment->uuid}/mark-successful", [
            'reason' => 'Bank transfer confirmed manually.',
        ])->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::Successful->value);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'action' => 'payments.manual_override',
        ]);
    }

    public function test_admin_can_batch_approve_applications(): void
    {
        Notification::fake();

        $admin = User::query()->where('email', 'admin@jocrams.test')->first();
        $tier = MembershipTier::query()->first();

        $applications = collect(range(1, 2))->map(function () use ($tier) {
            $user = User::factory()->create();

            return MembershipApplication::query()->create([
                'user_id' => $user->id,
                'membership_tier_id' => $tier->id,
                'status' => ApplicationStatus::UnderReview,
                'applicant_email' => $user->email,
                'applicant_name' => $user->name,
                'submitted_at' => now(),
            ]);
        });

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/batch/applications/approve', [
            'application_uuids' => $applications->pluck('uuid')->all(),
        ])->assertOk()
            ->assertJsonCount(2, 'data.approved');
    }

    public function test_notification_template_crud(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@jocrams.test')->first());

        $this->postJson('/api/v1/admin/notification-templates', [
            'slug' => 'custom-welcome',
            'name' => 'Custom Welcome',
            'channel' => 'email',
            'subject' => 'Welcome {{name}}',
            'body' => 'Hello {{name}}, welcome to {{app_name}}.',
        ])->assertCreated();

        $this->assertDatabaseHas('notification_templates', ['slug' => 'custom-welcome']);
    }

    public function test_renewal_reminder_command_sends_notifications(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $tier = MembershipTier::query()->first();

        Member::query()->create([
            'user_id' => $user->id,
            'membership_tier_id' => $tier->id,
            'membership_number' => 'MEM-RENEW01',
            'status' => MemberStatus::Active,
            'joined_at' => now()->subYear(),
            'expires_at' => now()->addDays(14),
        ]);

        $this->artisan('members:send-renewal-reminders', ['--days' => 30])
            ->assertSuccessful();

        Notification::assertSentTo($user, TemplatedMailNotification::class);
    }
}
