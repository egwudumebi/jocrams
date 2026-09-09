<?php

namespace Tests\Feature;

use App\Enums\JournalCallStatus;
use App\Enums\JournalIssueStatus;
use App\Enums\JournalVolumeStatus;
use App\Enums\MemberStatus;
use App\Models\JournalCallForPapers;
use App\Models\JournalIssue;
use App\Models\JournalSubmission;
use App\Models\JournalVolume;
use App\Models\Member;
use App\Models\MembershipTier;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\MembershipTierSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JournalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(MembershipTierSeeder::class);
        Storage::fake('local');
    }

    public function test_submission_rejects_pdf_and_accepts_docx(): void
    {
        $author = $this->createMemberUser();
        Sanctum::actingAs($author);

        $call = $this->createOpenCall();

        $this->post('/api/v1/journal/submissions', [
            'call_for_papers_uuid' => $call->uuid,
            'title' => 'Paper',
            'author_name' => 'Author',
            'author_email' => 'author@gmail.com',
            'abstract' => 'Abstract text for the paper.',
            'category' => 'research',
            'document' => UploadedFile::fake()->create('manuscript.pdf', 100, 'application/pdf'),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['document']);

        $this->post('/api/v1/journal/submissions', [
            'call_for_papers_uuid' => $call->uuid,
            'title' => 'Paper',
            'author_name' => 'Author',
            'author_email' => 'author@gmail.com',
            'abstract' => 'Abstract text for the paper.',
            'category' => 'research',
            'document' => $this->docxUpload(),
        ])->assertCreated();
    }

    public function test_reviewer_must_accept_assignment_before_reviewing(): void
    {
        [$author, $reviewer, $admin, $submission] = $this->createAssignedSubmission();

        Sanctum::actingAs($reviewer);

        $this->postJson("/api/v1/journal/submissions/{$submission->uuid}/review", [
            'decision' => 'accept',
            'review_comment' => 'Looks good.',
        ])->assertForbidden();

        $assignmentId = $this->assignmentIdFor($submission->uuid, $reviewer->uuid);

        $this->postJson("/api/v1/journal/submissions/{$submission->uuid}/assignment/respond", [
            'assignment_id' => $assignmentId,
            'decision' => 'accept',
        ])->assertOk();

        $this->postJson("/api/v1/journal/submissions/{$submission->uuid}/review", [
            'decision' => 'accept',
            'review_comment' => 'Looks good.',
        ])->assertOk();

        $this->assertDatabaseHas('journal_submissions', [
            'uuid' => $submission->uuid,
            'status' => 'approved',
            'reviewer_id' => $reviewer->uuid,
        ]);
    }

    public function test_resubmit_updates_document_path_and_preserves_reviewer(): void
    {
        [$author, $reviewer, $admin, $submission] = $this->createAssignedSubmission();

        Sanctum::actingAs($reviewer);
        $assignmentId = $this->assignmentIdFor($submission->uuid, $reviewer->uuid);
        $this->postJson("/api/v1/journal/submissions/{$submission->uuid}/assignment/respond", [
            'assignment_id' => $assignmentId,
            'decision' => 'accept',
        ])->assertOk();

        $this->postJson("/api/v1/journal/submissions/{$submission->uuid}/request-revision", [
            'note' => 'Please revise the methods section.',
        ])->assertOk();

        Sanctum::actingAs($author);

        $this->post("/api/v1/journal/submissions/{$submission->uuid}/resubmit", [
            'note' => 'Revised manuscript attached.',
            'document' => $this->docxUpload('revision.docx'),
        ])->assertOk();

        $submission->refresh();

        $this->assertSame('resubmitted', $submission->status->value);
        $this->assertSame($reviewer->uuid, $submission->reviewer_id);
        $this->assertStringContainsString('revision-2-revision.docx', $submission->document_path);
        $this->assertTrue(Storage::disk('local')->exists($submission->document_path));
    }

    public function test_production_editor_can_upload_production_document_for_approved_submission(): void
    {
        [$author, $reviewer, $admin, $submission] = $this->createAssignedSubmission();

        Sanctum::actingAs($reviewer);
        $assignmentId = $this->assignmentIdFor($submission->uuid, $reviewer->uuid);
        $this->postJson("/api/v1/journal/submissions/{$submission->uuid}/assignment/respond", [
            'assignment_id' => $assignmentId,
            'decision' => 'accept',
        ])->assertOk();

        $this->postJson("/api/v1/journal/submissions/{$submission->uuid}/review", [
            'decision' => 'accept',
            'review_comment' => 'Accepted.',
        ])->assertOk();

        Sanctum::actingAs($admin);

        $this->post("/api/v1/journal/submissions/{$submission->uuid}/production-document", [
            'document' => $this->docxUpload('production.docx'),
        ])->assertOk();

        $submission->refresh();

        $this->assertNotNull($submission->production_document_path);
        $this->assertTrue(Storage::disk('local')->exists($submission->production_document_path));
    }

    public function test_reviewer_queue_lists_assignment_metadata(): void
    {
        [$author, $reviewer, $admin, $submission] = $this->createAssignedSubmission();

        Sanctum::actingAs($reviewer);

        $this->getJson('/api/v1/journal/queue')
            ->assertOk()
            ->assertJsonPath('data.0.id', $submission->uuid)
            ->assertJsonPath('data.0.assignment_status', 'assigned');
    }

    public function test_comments_are_restricted_to_editorial_participants(): void
    {
        [$author, $reviewer, $admin, $submission] = $this->createAssignedSubmission();
        $outsider = $this->createMemberUser('outsider@gmail.com');

        Sanctum::actingAs($outsider);

        $this->getJson("/api/v1/journal/submissions/{$submission->uuid}/comments")
            ->assertForbidden();

        Sanctum::actingAs($author);

        $this->postJson("/api/v1/journal/submissions/{$submission->uuid}/comments", [
            'comment' => 'Thanks for the update.',
            'author_role' => 'author',
        ])->assertCreated();
    }

    private function docxUpload(string $name = 'manuscript.docx'): UploadedFile
    {
        return UploadedFile::fake()->create(
            $name,
            100,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        );
    }

    private function createOpenCall(): JournalCallForPapers
    {
        $volume = JournalVolume::query()->create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Volume One',
            'volume_number' => 1,
            'year' => 2026,
            'status' => JournalVolumeStatus::Published,
        ]);

        $issue = JournalIssue::query()->create([
            'uuid' => (string) Str::uuid(),
            'journal_volume_id' => $volume->id,
            'title' => 'Issue One',
            'issue_number' => 1,
            'status' => JournalIssueStatus::Open,
        ]);

        return JournalCallForPapers::query()->create([
            'uuid' => (string) Str::uuid(),
            'journal_issue_id' => $issue->id,
            'title' => 'Open Call',
            'slug' => 'open-call-'.Str::random(4),
            'status' => JournalCallStatus::Open,
            'submission_fee' => 0,
            'publication_fee' => 0,
            'currency' => 'NGN',
        ]);
    }

    /** @return array{0: User, 1: User, 2: User, 3: JournalSubmission} */
    private function createAssignedSubmission(): array
    {
        $author = $this->createMemberUser('author@gmail.com');
        $reviewer = $this->createReviewerUser('reviewer@gmail.com');
        $admin = User::query()->where('email', 'admin@jocrams.test')->firstOrFail();

        Sanctum::actingAs($author);
        $call = $this->createOpenCall();

        $response = $this->post('/api/v1/journal/submissions', [
            'call_for_papers_uuid' => $call->uuid,
            'title' => 'Assigned Paper',
            'author_name' => 'Author',
            'author_email' => 'author@gmail.com',
            'abstract' => 'Abstract text for assigned paper.',
            'category' => 'research',
            'document' => $this->docxUpload(),
        ])->assertCreated();

        $submission = JournalSubmission::query()->where('uuid', $response->json('submission_id'))->firstOrFail();

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/journal/submissions/{$submission->uuid}/assign", [
            'reviewer_id' => $reviewer->uuid,
            'priority' => 'normal',
        ])->assertOk();

        return [$author, $reviewer, $admin, $submission->fresh()];
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

    private function createReviewerUser(string $email): User
    {
        $user = User::factory()->create(['email' => $email]);
        $user->assignRole(Role::query()->where('slug', 'reviewer')->firstOrFail());

        return $user->fresh();
    }

    private function assignmentIdFor(string $submissionUuid, string $reviewerUuid): string
    {
        return (string) \DB::table('journal_reviewer_assignments')
            ->where('submission_id', $submissionUuid)
            ->where('reviewer_id', $reviewerUuid)
            ->where('status', 'assigned')
            ->value('id');
    }
}
