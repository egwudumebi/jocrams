<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Enums\Visibility;
use App\Models\Download;
use App\Models\MediaFile;
use App\Models\Member;
use App\Models\MembershipTier;
use App\Models\User;
use App\Services\Credentials\CredentialGenerationService;
use Database\Seeders\MembershipTierSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CredentialAndDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(MembershipTierSeeder::class);
        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_credential_verification_returns_valid_for_active_member(): void
    {
        $user = User::factory()->create();
        $tier = MembershipTier::query()->first();
        $member = Member::query()->create([
            'user_id' => $user->id,
            'membership_tier_id' => $tier->id,
            'membership_number' => 'MEM-VERIFY01',
            'status' => MemberStatus::Active,
            'joined_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $credential = app(CredentialGenerationService::class)->issueMembershipCard($member);

        $response = $this->getJson("/api/v1/public/verify/{$credential->verification_token}");

        $response->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('member.membership_number', 'MEM-VERIFY01');
    }

    public function test_member_can_get_signed_download_url_for_members_only_content(): void
    {
        $user = User::factory()->create();
        $tier = MembershipTier::query()->first();
        Member::query()->create([
            'user_id' => $user->id,
            'membership_tier_id' => $tier->id,
            'membership_number' => 'MEM-DOWNLOAD',
            'status' => MemberStatus::Active,
            'joined_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        Storage::disk('local')->put('library/test.pdf', 'test content');

        $media = MediaFile::query()->create([
            'uploaded_by' => $user->id,
            'disk' => 'local',
            'path' => 'library/test.pdf',
            'filename' => 'test.pdf',
            'original_filename' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
            'collection' => 'library',
            'visibility' => Visibility::Private,
        ]);

        $download = Download::query()->create([
            'media_file_id' => $media->id,
            'title' => 'Member Handbook',
            'slug' => 'member-handbook',
            'visibility' => Visibility::MembersOnly,
            'is_active' => true,
            'published_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/member/downloads/{$download->uuid}/signed-url");

        $response->assertOk()
            ->assertJsonStructure(['signed_url', 'expires_in_minutes']);
    }

    public function test_member_can_download_credential_pdf_via_signed_url_without_auth(): void
    {
        $user = User::factory()->create();
        $tier = MembershipTier::query()->first();
        $member = Member::query()->create([
            'user_id' => $user->id,
            'membership_tier_id' => $tier->id,
            'membership_number' => 'MEM-CRED001',
            'status' => MemberStatus::Active,
            'joined_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $credential = app(CredentialGenerationService::class)->issueMembershipCard($member);

        Sanctum::actingAs($user);

        $signedUrl = $this->getJson("/api/v1/member/credentials/{$credential->uuid}")
            ->assertOk()
            ->json('download_url');

        $this->assertNotEmpty($signedUrl);

        $response = $this->get($signedUrl);

        $response->assertOk();
        $response->assertHeader('content-disposition');
    }

    public function test_membership_card_pdf_includes_profile_image_when_available(): void
    {
        $user = User::factory()->create(['name' => 'Ada Lovelace']);
        $tier = MembershipTier::query()->first();
        $member = Member::query()->create([
            'user_id' => $user->id,
            'membership_tier_id' => $tier->id,
            'membership_number' => 'MEM-PHOTO01',
            'status' => MemberStatus::Active,
            'joined_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $imagePath = 'profile-images/'.$user->uuid.'/avatar.jpg';
        Storage::disk('public')->put($imagePath, UploadedFile::fake()->image('avatar.jpg')->getContent());

        \App\Models\UserProfile::query()->create([
            'user_id' => $user->uuid,
            'profile_image_path' => $imagePath,
        ]);

        $credential = app(CredentialGenerationService::class)->issueMembershipCard($member->fresh(['user.profile']));
        $pdf = app(CredentialGenerationService::class)->renderPdf($credential)->output();

        $this->assertNotEmpty($pdf);
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(5000, strlen($pdf));
    }
}
