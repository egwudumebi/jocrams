<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Mail\MembershipCredentialPackMail;
use App\Models\Member;
use App\Models\MembershipTier;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payments\PaymentService;
use Database\Seeders\MembershipTierSeeder;
use Database\Seeders\NotificationTemplateSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class MembershipCredentialPackDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(MembershipTierSeeder::class);
        $this->seed(NotificationTemplateSeeder::class);
    }

    public function test_successful_membership_payment_emails_credential_pack_with_three_pdfs(): void
    {
        Mail::fake();

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
            'reference' => 'PROOF_PACKMAIL001',
            'idempotency_key' => 'pack-mail-001',
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
            'gateway_reference' => 'PROOF_PACKMAIL001',
            'paid_at' => now()->toIso8601String(),
            'raw' => [],
        ]);

        Mail::assertQueued(MembershipCredentialPackMail::class, function (MembershipCredentialPackMail $mail) use ($user): bool {
            return count($mail->attachments()) === 3
                && $mail->member->user_id === $user->id;
        });
    }
}
