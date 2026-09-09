<?php

namespace Tests\Feature\Admin;

use App\Enums\MemberStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\Member;
use App\Models\MembershipTier;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\MembershipTierSeeder;
use Database\Seeders\NotificationTemplateSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Mail\PaymentReceiptMail;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(MembershipTierSeeder::class);
        $this->seed(NotificationTemplateSeeder::class);
    }

    public function test_admin_can_view_payment_stats(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@jocrams.test')->first());

        Payment::query()->create([
            'user_id' => User::factory()->create()->id,
            'gateway' => PaymentGateway::Paystack,
            'reference' => 'PAY-STATS-001',
            'idempotency_key' => 'stats-key-1',
            'amount' => 10000,
            'currency' => 'NGN',
            'status' => PaymentStatus::Successful,
            'purpose' => 'dues',
            'paid_at' => now(),
        ]);

        Payment::query()->create([
            'user_id' => User::factory()->create()->id,
            'gateway' => PaymentGateway::Flutterwave,
            'reference' => 'PAY-STATS-002',
            'idempotency_key' => 'stats-key-2',
            'amount' => 5000,
            'currency' => 'NGN',
            'status' => PaymentStatus::Pending,
            'purpose' => 'donation',
        ]);

        $this->getJson('/api/v1/admin/payments/stats')
            ->assertOk()
            ->assertJsonPath('data.total_revenue', 10000)
            ->assertJsonPath('data.pending_count', 1)
            ->assertJsonPath('data.successful_count', 1);
    }

    public function test_admin_can_record_manual_payment(): void
    {
        Mail::fake();

        $admin = User::query()->where('email', 'admin@jocrams.test')->first();
        $user = User::factory()->create();
        $tier = MembershipTier::query()->first();

        $member = Member::query()->create([
            'user_id' => $user->id,
            'membership_tier_id' => $tier->id,
            'membership_number' => 'MEM-PAY001',
            'status' => MemberStatus::Active,
            'joined_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/admin/payments', [
            'member_uuid' => $member->uuid,
            'amount' => 25000,
            'purpose' => 'dues',
            'payment_method' => 'bank_transfer',
            'transaction_reference' => 'BANK-TXN-001',
            'description' => 'Annual dues via bank transfer',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', PaymentStatus::Successful->value)
            ->assertJsonPath('data.gateway', PaymentGateway::Manual->value);

        $this->assertDatabaseHas('payments', [
            'reference' => 'BANK-TXN-001',
            'gateway' => PaymentGateway::Manual->value,
            'status' => PaymentStatus::Successful->value,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'action' => 'payments.recorded',
        ]);

        $member->refresh();
        $this->assertTrue($member->expires_at->isFuture());

        Mail::assertQueued(PaymentReceiptMail::class);
    }

    public function test_export_respects_status_filter(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@jocrams.test')->first());

        Payment::query()->create([
            'user_id' => User::factory()->create()->id,
            'gateway' => PaymentGateway::Manual,
            'reference' => 'PAY-EXPORT-001',
            'idempotency_key' => 'export-key-1',
            'amount' => 1000,
            'currency' => 'NGN',
            'status' => PaymentStatus::Successful,
            'purpose' => 'dues',
            'paid_at' => now(),
        ]);

        Payment::query()->create([
            'user_id' => User::factory()->create()->id,
            'gateway' => PaymentGateway::Paystack,
            'reference' => 'PAY-EXPORT-002',
            'idempotency_key' => 'export-key-2',
            'amount' => 2000,
            'currency' => 'NGN',
            'status' => PaymentStatus::Pending,
            'purpose' => 'donation',
        ]);

        $response = $this->get('/api/v1/admin/exports/payments?format=csv&status=successful');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('PAY-EXPORT-001', $response->streamedContent());
        $this->assertStringNotContainsString('PAY-EXPORT-002', $response->streamedContent());
    }
}
