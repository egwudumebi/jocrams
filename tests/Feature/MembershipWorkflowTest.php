<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\MemberStatus;
use App\Models\MembershipApplication;
use App\Models\MembershipTier;
use App\Models\User;
use Database\Seeders\MembershipTierSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MembershipWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(MembershipTierSeeder::class);
        Storage::fake('local');
    }

    public function test_member_can_register_and_create_application(): void
    {
        $tier = MembershipTier::query()->first();

        $response = $this->postJson('/api/v1/member/auth/register', [
            'name' => 'Jane Member',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['user', 'token']);

        $user = User::query()->where('email', 'jane@example.com')->first();
        Sanctum::actingAs($user);

        $applicationResponse = $this->postJson('/api/v1/member/applications', [
            'membership_tier_id' => $tier->id,
            'form_data' => ['organization' => 'Acme Corp'],
        ]);

        $applicationResponse->assertCreated()
            ->assertJsonPath('data.status', ApplicationStatus::Draft->value);
    }

    public function test_application_submission_creates_approval_queue_entry(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $tier = MembershipTier::query()->first();
        Sanctum::actingAs($user);

        $application = MembershipApplication::query()->create([
            'user_id' => $user->id,
            'membership_tier_id' => $tier->id,
            'status' => ApplicationStatus::Draft,
            'applicant_email' => $user->email,
            'applicant_name' => $user->name,
        ]);

        $this->postJson("/api/v1/member/applications/{$application->uuid}/documents", [
            'document' => UploadedFile::fake()->create('id.pdf', 100, 'application/pdf'),
            'document_type' => 'national_id',
        ])->assertCreated();

        $this->postJson("/api/v1/member/applications/{$application->uuid}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', ApplicationStatus::UnderReview->value);

        $this->assertDatabaseHas('approvals', [
            'approvable_type' => MembershipApplication::class,
            'approvable_id' => $application->id,
            'status' => 'pending',
        ]);
    }

    public function test_admin_can_approve_application_and_activate_member(): void
    {
        Notification::fake();

        $admin = User::query()->where('email', 'admin@jocrams.test')->first();
        $user = User::factory()->create();
        $tier = MembershipTier::query()->first();

        $application = MembershipApplication::query()->create([
            'user_id' => $user->id,
            'membership_tier_id' => $tier->id,
            'status' => ApplicationStatus::UnderReview,
            'applicant_email' => $user->email,
            'applicant_name' => $user->name,
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/applications/{$application->uuid}/approve", [
            'notes' => 'All documents verified.',
        ])->assertOk()
            ->assertJsonStructure(['data' => ['membership_number', 'status']]);

        $this->assertDatabaseHas('members', [
            'user_id' => $user->id,
            'status' => MemberStatus::Active->value,
        ]);

        $this->assertDatabaseHas('digital_credentials', [
            'member_id' => $user->fresh()->member->id,
            'type' => 'membership_card',
        ]);
    }
}
