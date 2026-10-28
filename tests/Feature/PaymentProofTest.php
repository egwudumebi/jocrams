<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Enums\PaymentProofStatus;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\JournalSubmission;
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
            'amount' => 25000,
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
            'amount' => 25000,
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
            'amount' => 25000,
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

    public function test_membership_proof_ignores_a_missing_related_reference(): void
    {
        $user = $this->createMemberUser();
        Sanctum::actingAs($user);

        $this->post('/api/v1/member/payment-proofs', [
            'purpose' => PaymentPurpose::Dues->value,
            'amount' => 25000,
            'related_uuid' => 'undefined',
            'receipt' => UploadedFile::fake()->create('receipt.pdf', 80, 'application/pdf'),
        ], [
            'Accept' => 'application/json',
        ])->assertCreated();
    }

    public function test_journal_proof_matches_the_only_manuscript_without_a_uuid(): void
    {
        $user = $this->createMemberUser();
        $submission = $this->createSubmission($user, 'only-manuscript');
        Sanctum::actingAs($user);

        $this->post('/api/v1/member/payment-proofs', [
            'purpose' => PaymentPurpose::JournalSubmissionFee->value,
            'amount' => 10000,
            'receipt' => UploadedFile::fake()->create('receipt.pdf', 80, 'application/pdf'),
        ], [
            'Accept' => 'application/json',
        ])->assertCreated()
            ->assertJsonPath('data.related.uuid', $submission->uuid)
            ->assertJsonPath('data.related.title', 'Only manuscript');

        $this->assertSame($submission->id, PaymentProof::query()->firstOrFail()->related_id);
    }

    public function test_journal_proof_accepts_a_manuscript_slug(): void
    {
        $user = $this->createMemberUser('slug-proof@example.com');
        $submission = $this->createSubmission($user, 'slug-manuscript', 'payment_pending');
        $this->createSubmission($user, 'other-manuscript', 'submitted');
        Sanctum::actingAs($user);

        $this->post('/api/v1/member/payment-proofs', [
            'purpose' => PaymentPurpose::JournalSubmissionFee->value,
            'amount' => 10000,
            'related_uuid' => 'slug-manuscript',
            'receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ], [
            'Accept' => 'application/json',
        ])->assertCreated()
            ->assertJsonPath('data.related.uuid', $submission->uuid);
    }

    public function test_journal_proof_asks_for_a_manuscript_when_several_match(): void
    {
        $user = $this->createMemberUser('many-proof@example.com');
        $this->createSubmission($user, 'first-manuscript');
        $this->createSubmission($user, 'second-manuscript');
        Sanctum::actingAs($user);

        $this->post('/api/v1/member/payment-proofs', [
            'purpose' => PaymentPurpose::JournalSubmissionFee->value,
            'amount' => 10000,
            'receipt' => UploadedFile::fake()->create('receipt.pdf', 40, 'application/pdf'),
        ], [
            'Accept' => 'application/json',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.related_uuid.0', 'Choose the manuscript this payment is for.')
            ->assertJsonMissing(['message' => 'The related uuid field must be a valid UUID.']);
    }

    public function test_event_proof_accepts_a_registration_number(): void
    {
        $user = $this->createMemberUser('event-proof@example.com');
        $event = Event::query()->create([
            'organizer_id' => $user->id,
            'title' => 'Annual Meeting',
            'slug' => 'annual-meeting-proof',
            'starts_at' => now()->addMonth(),
            'status' => 'published',
        ]);
        $registration = EventRegistration::query()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'registrant_name' => $user->name,
            'registrant_email' => $user->email,
            'status' => 'pending',
            'registration_number' => 'EVT-PROOF-1001',
        ]);
        Sanctum::actingAs($user);

        $this->post('/api/v1/member/payment-proofs', [
            'purpose' => PaymentPurpose::EventFee->value,
            'amount' => 5000,
            'related_uuid' => 'EVT-PROOF-1001',
            'receipt' => UploadedFile::fake()->create('receipt.pdf', 40, 'application/pdf'),
        ], [
            'Accept' => 'application/json',
        ])->assertCreated()
            ->assertJsonPath('data.related.uuid', $registration->uuid);

        $this->getJson('/api/v1/member/payment-proofs/targets')
            ->assertOk()
            ->assertJsonPath('data.event_registrations.0.uuid', $registration->uuid);
    }

    private function createSubmission(User $user, string $slug, string $status = 'payment_pending'): JournalSubmission
    {
        return JournalSubmission::query()->create([
            'author_id' => $user->uuid,
            'title' => str_replace('-', ' ', ucfirst($slug)),
            'slug' => $slug,
            'author_name' => $user->name,
            'author_email' => $user->email,
            'abstract' => 'Abstract',
            'category' => 'Research',
            'document_path' => "journal/{$slug}.docx",
            'status' => $status,
        ]);
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
