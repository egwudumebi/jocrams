<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Models\Member;
use App\Models\MembershipTier;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payments\PaymentService;
use Database\Seeders\MembershipTierSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MembershipCredentialPackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(MembershipTierSeeder::class);
    }

    public function test_successful_dues_payment_issues_id_certificate_and_welcome_letter(): void
    {
        $user = User::factory()->create(['email' => 'paid-member@gmail.com']);
        $tier = MembershipTier::query()->where('slug', 'sicama')->firstOrFail();

        $member = Member::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'membership_tier_id' => $tier->id,
            'membership_number' => 'MEM-TEMPTEST1',
            'status' => MemberStatus::Active,
            'joined_at' => now(),
            'expires_at' => now()->addMonths(6),
            'approved_at' => now(),
            'metadata' => ['organization' => 'sicama', 'awaiting_membership_payment' => true],
        ]);

        $subscription = Subscription::query()->create([
            'member_id' => $member->id,
            'membership_tier_id' => $tier->id,
            'starts_at' => now(),
            'ends_at' => now()->addYear(),
            'status' => 'pending',
            'amount' => 25000,
            'currency' => 'NGN',
        ]);

        $payment = Payment::query()->create([
            'user_id' => $user->id,
            'member_id' => $member->id,
            'payable_type' => $subscription->getMorphClass(),
            'payable_id' => $subscription->id,
            'gateway' => PaymentGateway::Manual,
            'reference' => 'PROOF_TESTPACK001',
            'idempotency_key' => 'pack-test-001',
            'amount' => 25000,
            'currency' => 'NGN',
            'status' => PaymentStatus::Pending,
            'purpose' => PaymentPurpose::Dues,
            'description' => 'SICAMA membership fee',
        ]);

        app(PaymentService::class)->markSuccessful($payment, [
            'status' => 'successful',
            'amount' => 25000,
            'currency' => 'NGN',
            'gateway_reference' => 'PROOF_TESTPACK001',
            'paid_at' => now()->toIso8601String(),
            'raw' => [],
        ]);

        $member->refresh();

        $this->assertTrue(str_starts_with((string) $member->membership_number, 'SICAMA '));
        $this->assertDatabaseHas('digital_credentials', [
            'member_id' => $member->id,
            'type' => 'membership_card',
        ]);
        $this->assertDatabaseHas('digital_credentials', [
            'member_id' => $member->id,
            'type' => 'certificate',
        ]);
        $this->assertDatabaseHas('digital_credentials', [
            'member_id' => $member->id,
            'type' => 'welcome_letter',
        ]);
        $this->assertNull(data_get($member->metadata, 'awaiting_membership_payment'));
    }
}
