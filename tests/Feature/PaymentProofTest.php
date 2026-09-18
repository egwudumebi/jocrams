<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Enums\PaymentProofStatus;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Models\Member;
use App\Models\MembershipTier;
use App\Models\PaymentProof;
use App\Models\User;
use Database\Seeders\MembershipTierSeeder;
use Database\Seeders\NotificationTemplateSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentProofTest extends TestCase
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

    public function test_member_can_submit_bank_transfer_receipt_for_dues(): void
    {
        $user = $this->createMemberUser();

        Sanctum::actingAs($user);

        $response = $this->post('/api/v1/member/payment-proofs', [
            'purpose' => PaymentPurpose::Dues->value,
            'amount' => 25000,
            'payer_reference' => 'UBA-REF-001',
            'member_note' => 'Paid annual dues',
            'receipt' => UploadedFile::fake()->create('receipt.pdf', 120, 'application/pdf'),
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.purpose', 'dues')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.payer_reference', 'UBA-REF-001');

        $this->assertDatabaseHas('payment_proofs', [
            'user_id' => $user->id,
            'purpose' => 'dues',
            'status' => PaymentProofStatus::Pending->value,
        ]);
    }

    public function test_admin_can_approve_payment_proof_and_record_manual_payment(): void
    {
        Mail::fake();

        $user = $this->createMemberUser();
        Sanctum::actingAs($user);

        $this->post('/api/v1/member/payment-proofs', [
            'purpose' => PaymentPurpose::Registration->value,
            'amount' => 20000,
            'receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ], [
            'Accept' => 'application/json',
        ])->assertCreated();

        $proof = PaymentProof::query()->firstOrFail();
        $admin = User::query()->where('email', 'admin@jocrams.test')->firstOrFail();

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/payment-proofs/{$proof->uuid}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $proof->refresh();

        $this->assertSame(PaymentProofStatus::Approved, $proof->status);
        $this->assertNotNull($proof->payment_id);
        $this->assertDatabaseHas('payments', [
            'id' => $proof->payment_id,
            'gateway' => 'manual',
            'status' => PaymentStatus::Successful->value,
            'purpose' => PaymentPurpose::Registration->value,
        ]);
    }

    public function test_admin_can_reject_payment_proof(): void
    {
        $user = $this->createMemberUser();
        Sanctum::actingAs($user);

        $this->post('/api/v1/member/payment-proofs', [
            'purpose' => PaymentPurpose::Dues->value,
            'amount' => 15000,
            'receipt' => UploadedFile::fake()->create('receipt.pdf', 80, 'application/pdf'),
        ], [
            'Accept' => 'application/json',
        ])->assertCreated();

        $proof = PaymentProof::query()->firstOrFail();
        $admin = User::query()->where('email', 'admin@jocrams.test')->firstOrFail();

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/payment-proofs/{$proof->uuid}/reject", [
            'reason' => 'Amount does not match transfer',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('payment_proofs', [
            'id' => $proof->id,
            'status' => PaymentProofStatus::Rejected->value,
            'rejection_reason' => 'Amount does not match transfer',
        ]);
    }

    public function test_admin_can_list_pending_payment_proofs(): void
    {
        $user = $this->createMemberUser();
        Sanctum::actingAs($user);

        $this->post('/api/v1/member/payment-proofs', [
            'purpose' => PaymentPurpose::Dues->value,
            'amount' => 10000,
            'receipt' => UploadedFile::fake()->image('proof.png'),
        ], [
            'Accept' => 'application/json',
        ])->assertCreated();

        $admin = User::query()->where('email', 'admin@jocrams.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/payment-proofs?status=pending')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonPath('data.0.user.email', $user->email);

        $this->getJson('/api/v1/admin/payment-proofs/pending-count')
            ->assertOk()
            ->assertJsonPath('data.pending', 1);
    }

    private function createMemberUser(string $email = 'member-proof@example.com'): User
    {
        $user = User::factory()->create(['email' => $email]);
        $tier = MembershipTier::query()->firstOrFail();

        Member::query()->create([
            'user_id' => $user->id,
            'membership_tier_id' => $tier->id,
            'membership_number' => 'MEM-PROOF-'.substr(md5($email), 0, 6),
            'status' => MemberStatus::Active,
            'joined_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        return $user->fresh(['member.tier']);
    }
}
