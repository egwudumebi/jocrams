<?php

namespace Tests\Feature;

use App\Enums\JournalCallStatus;
use App\Enums\JournalIssueStatus;
use App\Enums\JournalVolumeStatus;
use App\Enums\MemberStatus;
use App\Models\EditorialBoardMember;
use App\Models\JournalCallForPapers;
use App\Models\JournalIssue;
use App\Models\JournalVolume;
use App\Models\Member;
use App\Models\MembershipTier;
use App\Models\User;
use Database\Seeders\MembershipTierSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JournalCallForPapersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(MembershipTierSeeder::class);
        Storage::fake('local');
    }

    public function test_admin_can_manage_volumes_and_issues(): void
    {
        $admin = User::query()->where('email', 'admin@jocrams.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $volumeResponse = $this->postJson('/api/v1/journal/admin/volumes', [
            'title' => 'Annual Research',
            'volume_number' => 1,
            'year' => 2026,
            'status' => JournalVolumeStatus::Published->value,
        ]);

        $volumeResponse->assertCreated()
            ->assertJsonPath('data.volume_number', 1);

        $volumeUuid = $volumeResponse->json('data.uuid');

        $issueResponse = $this->postJson('/api/v1/journal/admin/issues', [
            'journal_volume_uuid' => $volumeUuid,
            'title' => 'Spring Issue',
            'issue_number' => 1,
            'status' => JournalIssueStatus::Open->value,
        ]);

        $issueResponse->assertCreated()
            ->assertJsonPath('data.issue_number', 1)
            ->assertJsonPath('data.volume.uuid', $volumeUuid);

        $this->assertDatabaseHas('journal_volumes', [
            'volume_number' => 1,
            'year' => 2026,
        ]);

        $this->assertDatabaseHas('journal_issues', [
            'title' => 'Spring Issue',
            'issue_number' => 1,
        ]);
    }

    public function test_admin_can_create_call_with_fees_and_editorial_board(): void
    {
        $admin = User::query()->where('email', 'admin@jocrams.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $issue = $this->createIssue();

        $member = EditorialBoardMember::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Dr. Ada Okafor',
            'role_title' => 'Editor-in-Chief',
            'affiliation' => 'University of Lagos',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/journal/admin/calls-for-papers', [
            'journal_issue_uuid' => $issue->uuid,
            'title' => 'Education Research Special Issue',
            'excerpt' => 'Submit your education research manuscripts.',
            'body' => '<p>Guidelines for authors.</p>',
            'submission_fee' => 5000,
            'publication_fee' => 15000,
            'currency' => 'NGN',
            'status' => 'open',
            'editorial_board_member_ids' => [$member->uuid],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Education Research Special Issue')
            ->assertJsonPath('data.submission_fee', 5000)
            ->assertJsonPath('data.publication_fee', 15000)
            ->assertJsonPath('data.issue.uuid', $issue->uuid);

        $this->assertDatabaseHas('journal_calls_for_papers', [
            'title' => 'Education Research Special Issue',
            'status' => JournalCallStatus::Open->value,
            'journal_issue_id' => $issue->id,
        ]);

        $call = JournalCallForPapers::query()->firstOrFail();
        $this->assertTrue($call->editorialBoardMembers()->where('editorial_board_members.id', $member->id)->exists());
    }

    public function test_member_sees_open_calls_with_issue(): void
    {
        $author = $this->createMemberUser();
        Sanctum::actingAs($author);

        $issue = $this->createIssue();

        JournalCallForPapers::query()->create([
            'uuid' => (string) Str::uuid(),
            'journal_issue_id' => $issue->id,
            'title' => 'Open Call',
            'slug' => 'open-call',
            'status' => JournalCallStatus::Open,
            'submission_fee' => 1000,
            'publication_fee' => 0,
            'currency' => 'NGN',
        ]);

        $this->getJson('/api/v1/journal/calls/open')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Open Call')
            ->assertJsonPath('data.0.issue.uuid', $issue->uuid);
    }

    public function test_submission_with_fee_requires_payment_and_links_issue(): void
    {
        $author = $this->createMemberUser('author@example.com');
        Sanctum::actingAs($author);

        $issue = $this->createIssue();

        $call = JournalCallForPapers::query()->create([
            'uuid' => (string) Str::uuid(),
            'journal_issue_id' => $issue->id,
            'title' => 'Paid Call',
            'slug' => 'paid-call',
            'status' => JournalCallStatus::Open,
            'submission_fee' => 2500,
            'publication_fee' => 5000,
            'currency' => 'NGN',
        ]);

        $file = UploadedFile::fake()->create(
            'manuscript.docx',
            100,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        );

        $response = $this->post('/api/v1/journal/submissions', [
            'call_for_papers_uuid' => $call->uuid,
            'title' => 'My Research Paper',
            'author_name' => 'Test Author',
            'author_email' => 'author@gmail.com',
            'abstract' => 'This is a valid abstract for testing.',
            'category' => 'research',
            'document' => $file,
        ]);

        $response->assertCreated()
            ->assertJsonPath('requires_payment', true)
            ->assertJsonPath('submission_fee', 2500)
            ->assertJsonPath('publication_fee', 5000);

        $this->assertDatabaseHas('journal_submissions', [
            'title' => 'My Research Paper',
            'status' => 'payment_pending',
            'call_for_papers_id' => $call->id,
            'journal_issue_id' => $issue->id,
            'submission_fee_amount' => 2500,
            'publication_fee_amount' => 5000,
        ]);
    }

    public function test_submission_rejected_when_no_open_call_exists(): void
    {
        $author = $this->createMemberUser('author@gmail.com');
        Sanctum::actingAs($author);

        $file = UploadedFile::fake()->create(
            'manuscript.docx',
            100,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        );

        $this->post('/api/v1/journal/submissions', [
            'title' => 'My Research Paper',
            'author_name' => 'Test Author',
            'author_email' => 'author@gmail.com',
            'abstract' => 'This is a valid abstract for testing.',
            'category' => 'research',
            'document' => $file,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['call_for_papers_uuid']);
    }

    public function test_call_requires_journal_issue(): void
    {
        $admin = User::query()->where('email', 'admin@jocrams.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/journal/admin/calls-for-papers', [
            'title' => 'Missing Issue Call',
            'status' => 'open',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['journal_issue_uuid']);
    }

    private function createIssue(): JournalIssue
    {
        $volume = JournalVolume::query()->create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Volume One',
            'volume_number' => 1,
            'year' => 2026,
            'status' => JournalVolumeStatus::Published,
        ]);

        return JournalIssue::query()->create([
            'uuid' => (string) Str::uuid(),
            'journal_volume_id' => $volume->id,
            'title' => 'Issue One',
            'issue_number' => 1,
            'status' => JournalIssueStatus::Open,
        ]);
    }

    private function createMemberUser(string $email = 'author@example.com'): User
    {
        $user = User::factory()->create(['email' => $email]);
        $tier = MembershipTier::query()->firstOrFail();

        Member::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'membership_tier_id' => $tier->id,
            'membership_number' => 'MEM-'.random_int(1000, 9999),
            'status' => MemberStatus::Active,
            'joined_at' => now(),
            'expires_at' => now()->addYear(),
            'approved_at' => now(),
        ]);

        return $user->fresh(['member']);
    }
}
