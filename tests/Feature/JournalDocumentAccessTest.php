<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Mail\JournalNotificationMail;
use App\Models\Member;
use App\Models\MembershipTier;
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

class JournalDocumentAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(MembershipTierSeeder::class);
        Storage::fake('local');
    }

    public function test_guest_list_does_not_expose_submitted_document_url(): void
    {
        $author = $this->createMemberUser();
        $submissionUuid = '596adf18-3adc-401d-a858-f3db001e68da';
        $this->insertJournalSubmission($submissionUuid, (string) $author->uuid, 'submitted', 'all');

        $response = $this->getJson('/api/v1/journal/submissions');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $submissionUuid)
            ->assertJsonPath('data.0.can_access', false)
            ->assertJsonPath('data.0.document_url', null);
    }

    public function test_guest_list_exposes_approved_public_document_url(): void
    {
        $author = $this->createMemberUser();
        $submissionUuid = 'fefd78b6-a8ef-4603-b09b-a175040aac4f';
        $this->insertJournalSubmission($submissionUuid, (string) $author->uuid, 'approved', 'all');

        $response = $this->getJson('/api/v1/journal/submissions');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $submissionUuid)
            ->assertJsonPath('data.0.can_access', true)
            ->assertJsonPath('data.0.document_url', url('/api/v1/journal/submissions/test-journal/document'));
    }

    public function test_author_list_exposes_own_submitted_document_url(): void
    {
        $author = $this->createMemberUser();
        $submissionUuid = '302e5bf1-b041-4a25-b4db-57ce24dd7627';
        $this->insertJournalSubmission($submissionUuid, (string) $author->uuid, 'submitted', 'all');

        Sanctum::actingAs($author);

        $response = $this->getJson('/api/v1/journal/submissions');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $submissionUuid)
            ->assertJsonPath('data.0.can_access', true)
            ->assertJsonPath('data.0.document_url', url('/api/v1/journal/submissions/test-journal/document'));
    }

    public function test_admin_with_journal_assign_can_download_submitted_document(): void
    {
        $author = $this->createMemberUser();
        $admin = User::query()->where('email', 'admin@jocrams.test')->firstOrFail();
        $submissionUuid = 'c45c47f8-c6b0-4c84-a1b5-a816294b1b1e';
        $documentPath = 'journal-submissions/'.$submissionUuid.'/document.pdf';

        $this->insertJournalSubmission($submissionUuid, (string) $author->uuid, 'submitted', 'all', $documentPath);
        Storage::disk('local')->put($documentPath, '%PDF-1.4 test');

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/journal/submissions/'.$submissionUuid.'/document')
            ->assertOk();

        $this->getJson('/api/v1/journal/submissions')
            ->assertOk()
            ->assertJsonPath('data.0.can_access', true);
    }

    public function test_active_member_can_download_members_only_approved_document(): void
    {
        $author = $this->createMemberUser();
        $member = $this->createMemberUser('member@example.com');
        $submissionUuid = 'fefd78b6-a8ef-4603-b09b-a175040aac4f';
        $documentPath = 'journal-submissions/'.$submissionUuid.'/document.pdf';

        $this->insertJournalSubmission($submissionUuid, (string) $author->uuid, 'approved', 'members_only', $documentPath);
        Storage::disk('local')->put($documentPath, '%PDF-1.4 test');

        Sanctum::actingAs($member);

        $this->getJson('/api/v1/journal/submissions/'.$submissionUuid.'/document')
            ->assertOk();

        $this->getJson('/api/v1/journal/submissions/browse')
            ->assertOk()
            ->assertJsonPath('data.0.can_access', true);
    }

    public function test_guest_document_download_returns_forbidden_not_found(): void
    {
        $author = $this->createMemberUser();
        $submissionUuid = '596adf18-3adc-401d-a858-f3db001e68da';
        $documentPath = 'journal-submissions/'.$submissionUuid.'/document.pdf';

        $this->insertJournalSubmission($submissionUuid, (string) $author->uuid, 'submitted', 'all', $documentPath);
        Storage::disk('local')->put($documentPath, '%PDF-1.4 test');

        $this->getJson('/api/v1/journal/submissions/'.$submissionUuid.'/document')
            ->assertForbidden()
            ->assertJsonPath('message', 'You do not have permission to access this document. Sign in and send your access token, or use an account with an active membership.');
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

    private function insertJournalSubmission(
        string $submissionUuid,
        string $authorUuid,
        string $status,
        string $visibility,
        ?string $documentPath = null,
    ): void {
        DB::table('journal_submissions')->insert([
            'uuid' => $submissionUuid,
            'author_id' => $authorUuid,
            'title' => 'Test journal',
            'slug' => JournalSlugGenerator::uniqueForTitle('Test journal', $submissionUuid),
            'author_name' => 'Test Author',
            'author_email' => 'author@example.com',
            'abstract' => 'A test abstract.',
            'category' => 'research',
            'keywords' => 'test',
            'document_path' => $documentPath ?? 'journal-submissions/'.$submissionUuid.'/document.pdf',
            'status' => $status,
            'visibility' => $visibility,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
