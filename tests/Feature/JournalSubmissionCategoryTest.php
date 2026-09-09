<?php

namespace Tests\Feature;

use App\Enums\JournalCallStatus;
use App\Enums\JournalIssueStatus;
use App\Enums\JournalVolumeStatus;
use App\Enums\MemberStatus;
use App\Models\Category;
use App\Models\JournalCallForPapers;
use App\Models\JournalIssue;
use App\Models\JournalVolume;
use App\Models\Member;
use App\Models\MembershipTier;
use App\Models\User;
use App\Services\Journal\JournalSubmissionCategoryService;
use Database\Seeders\MembershipTierSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JournalSubmissionCategoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(MembershipTierSeeder::class);
        Storage::fake('local');
    }

    public function test_admin_can_create_journal_category(): void
    {
        $admin = User::query()->where('email', 'admin@jocrams.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/journal/admin/categories', [
            'name' => 'Education Research',
            'description' => 'Studies in education policy and practice',
            'sort_order' => 1,
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Education Research');

        $this->assertDatabaseHas('categories', [
            'name' => 'Education Research',
            'type' => JournalSubmissionCategoryService::TYPE,
            'is_active' => true,
        ]);
    }

    public function test_member_sees_active_categories(): void
    {
        $author = $this->createMemberUser();
        Sanctum::actingAs($author);

        Category::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Research',
            'slug' => 'research',
            'type' => JournalSubmissionCategoryService::TYPE,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Category::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Inactive Topic',
            'slug' => 'inactive-topic',
            'type' => JournalSubmissionCategoryService::TYPE,
            'sort_order' => 2,
            'is_active' => false,
        ]);

        $this->getJson('/api/v1/journal/categories')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Research');
    }

    public function test_submission_rejects_invalid_category_when_list_exists(): void
    {
        $author = $this->createMemberUser('author@gmail.com');
        Sanctum::actingAs($author);

        Category::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Research',
            'slug' => 'research',
            'type' => JournalSubmissionCategoryService::TYPE,
            'is_active' => true,
        ]);

        $file = UploadedFile::fake()->create(
            'manuscript.docx',
            100,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        );

        $call = $this->createOpenCall();

        $this->post('/api/v1/journal/submissions', [
            'call_for_papers_uuid' => $call->uuid,
            'title' => 'Paper Title',
            'author_name' => 'Test Author',
            'author_email' => 'author@gmail.com',
            'abstract' => 'Valid abstract for category validation test.',
            'category' => 'Made Up Category',
            'document' => $file,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['category']);
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
}
