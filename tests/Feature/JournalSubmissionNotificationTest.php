<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Mail\JournalNotificationMail;
use App\Models\Member;
use App\Models\MembershipTier;
use App\Models\Role;
use App\Models\User;
use App\Services\Journal\JournalSlugGenerator;
use Database\Seeders\MembershipTierSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JournalSubmissionNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(MembershipTierSeeder::class);
        Storage::fake('local');
    }

    public function test_rejecting_submission_emails_author(): void
    {
        Mail::fake();

        $author = $this->createMemberUser('author@example.com');
        $reviewer = $this->createReviewerUser();
        $submissionUuid = (string) Str::uuid();

        $this->insertJournalSubmission(
            $submissionUuid,
            (string) $author->uuid,
            'author@example.com',
            'under_review',
            (string) $reviewer->uuid,
            acceptedAssignment: true,
        );

        Sanctum::actingAs($reviewer);

        $this->postJson('/api/v1/journal/submissions/'.$submissionUuid.'/review', [
            'decision' => 'reject',
            'rejection_reason' => 'Scope does not fit the journal.',
            'review_comment' => 'Please consider another venue.',
        ])->assertOk();

        Mail::assertSent(JournalNotificationMail::class, function (JournalNotificationMail $mail): bool {
            return $mail->hasTo('author@example.com');
        });

        $this->assertDatabaseHas('notification_logs', [
            'recipient' => 'author@example.com',
            'event' => 'journal.submission.rejected',
            'channel' => 'email',
        ]);

        $this->assertDatabaseHas('notification_logs', [
            'recipient' => 'author@example.com',
            'event' => 'journal.submission.rejected',
            'channel' => 'in_app',
        ]);
    }

    public function test_assigning_reviewer_emails_author_and_reviewer(): void
    {
        Mail::fake();

        $author = $this->createMemberUser('author@example.com');
        $reviewer = $this->createReviewerUser('reviewer@example.com');
        $admin = User::query()->where('email', 'admin@jocrams.test')->firstOrFail();
        $submissionUuid = (string) Str::uuid();

        $this->insertJournalSubmission($submissionUuid, (string) $author->uuid, 'author@example.com');

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/journal/submissions/'.$submissionUuid.'/assign', [
            'reviewer_id' => (string) $reviewer->uuid,
            'priority' => 'normal',
            'due_at' => '2026-08-01',
        ])->assertOk();

        Mail::assertSent(JournalNotificationMail::class, fn (JournalNotificationMail $mail): bool => $mail->hasTo('author@example.com'));
        Mail::assertSent(JournalNotificationMail::class, fn (JournalNotificationMail $mail): bool => $mail->hasTo('reviewer@example.com'));

        $this->assertDatabaseHas('notification_logs', [
            'recipient' => 'author@example.com',
            'event' => 'journal.submission.under_review',
        ]);

        $this->assertDatabaseHas('notification_logs', [
            'recipient' => 'reviewer@example.com',
            'event' => 'journal.reviewer.assignment',
        ]);
    }

    public function test_request_revision_emails_author(): void
    {
        Mail::fake();

        $author = $this->createMemberUser('author@example.com');
        $reviewer = $this->createReviewerUser();
        $submissionUuid = (string) Str::uuid();

        $this->insertJournalSubmission(
            $submissionUuid,
            (string) $author->uuid,
            'author@example.com',
            'under_review',
            (string) $reviewer->uuid,
            acceptedAssignment: true,
        );

        Sanctum::actingAs($reviewer);

        $this->postJson('/api/v1/journal/submissions/'.$submissionUuid.'/request-revision', [
            'note' => 'Please expand the methodology section.',
        ])->assertOk();

        Mail::assertSent(JournalNotificationMail::class, fn (JournalNotificationMail $mail): bool => $mail->hasTo('author@example.com'));

        $this->assertDatabaseHas('notification_logs', [
            'recipient' => 'author@example.com',
            'event' => 'journal.submission.revision_requested',
        ]);
    }

    private function createMemberUser(string $email): User
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

    private function createReviewerUser(string $email = 'reviewer@example.com'): User
    {
        $user = User::factory()->create(['email' => $email]);
        $reviewerRole = Role::query()->where('slug', 'reviewer')->firstOrFail();
        $user->assignRole($reviewerRole);

        return $user;
    }

    private function insertJournalSubmission(
        string $submissionUuid,
        string $authorUuid,
        string $authorEmail,
        string $status = 'submitted',
        ?string $reviewerUuid = null,
        bool $acceptedAssignment = false,
    ): void {
        DB::table('journal_submissions')->insert([
            'uuid' => $submissionUuid,
            'author_id' => $authorUuid,
            'title' => 'Sample Manuscript',
            'slug' => JournalSlugGenerator::uniqueForTitle('Sample Manuscript', $submissionUuid),
            'author_name' => 'Test Author',
            'author_email' => $authorEmail,
            'abstract' => 'A test abstract.',
            'category' => 'research',
            'keywords' => 'test',
            'document_path' => 'journal-submissions/'.$submissionUuid.'/document.pdf',
            'status' => $status,
            'visibility' => 'all',
            'reviewer_id' => $reviewerUuid,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($reviewerUuid !== null && $acceptedAssignment) {
            DB::table('journal_reviewer_assignments')->insert([
                'id' => (string) Str::uuid(),
                'submission_id' => $submissionUuid,
                'reviewer_id' => $reviewerUuid,
                'assigned_by' => $reviewerUuid,
                'priority' => 'normal',
                'status' => 'accepted',
                'assigned_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
