<?php

namespace Tests\Feature\Admin;

use App\Models\Download;
use App\Models\User;
use Database\Seeders\MembershipTierSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DownloadCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(MembershipTierSeeder::class);
        Storage::fake('local');
    }

    public function test_admin_can_create_public_download(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@jocrams.test')->first());

        $response = $this->post('/api/v1/admin/downloads', [
            'title' => 'Annual Report 2026',
            'description' => 'Association annual report.',
            'visibility' => 'public',
            'is_active' => true,
            'file' => UploadedFile::fake()->create('report.pdf', 100, 'application/pdf'),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Annual Report 2026')
            ->assertJsonPath('data.visibility', 'public')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('downloads', [
            'title' => 'Annual Report 2026',
            'visibility' => 'public',
            'is_active' => true,
        ]);
    }

    public function test_public_download_appears_on_public_listing(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@jocrams.test')->first());

        $this->post('/api/v1/admin/downloads', [
            'title' => 'Public Handbook',
            'visibility' => 'public',
            'is_active' => true,
            'file' => UploadedFile::fake()->create('handbook.pdf', 100, 'application/pdf'),
        ])->assertCreated();

        $response = $this->getJson('/api/v1/public/downloads');

        $response->assertOk()
            ->assertJsonFragment(['title' => 'Public Handbook']);
    }

    public function test_deactivated_download_is_hidden_from_public_listing(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@jocrams.test')->first());

        $create = $this->post('/api/v1/admin/downloads', [
            'title' => 'Archived Guide',
            'visibility' => 'public',
            'is_active' => true,
            'file' => UploadedFile::fake()->create('guide.pdf', 100, 'application/pdf'),
        ])->assertCreated();

        $uuid = $create->json('data.uuid');

        $this->putJson("/api/v1/admin/downloads/{$uuid}", [
            'is_active' => false,
        ])->assertOk();

        $response = $this->getJson('/api/v1/public/downloads');

        $response->assertOk()
            ->assertJsonMissing(['title' => 'Archived Guide']);
    }

    public function test_admin_can_update_and_delete_download(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@jocrams.test')->first());

        $create = $this->post('/api/v1/admin/downloads', [
            'title' => 'Draft Policy',
            'visibility' => 'members_only',
            'is_active' => true,
            'file' => UploadedFile::fake()->create('policy.pdf', 100, 'application/pdf'),
        ])->assertCreated();

        $uuid = $create->json('data.uuid');

        $this->putJson("/api/v1/admin/downloads/{$uuid}", [
            'title' => 'Updated Policy',
            'visibility' => 'public',
        ])->assertOk()
            ->assertJsonPath('data.title', 'Updated Policy')
            ->assertJsonPath('data.visibility', 'public');

        $this->deleteJson("/api/v1/admin/downloads/{$uuid}")
            ->assertOk();

        $this->assertSoftDeleted('downloads', ['uuid' => $uuid]);
    }

    public function test_tier_specific_download_requires_allowed_tier_ids(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@jocrams.test')->first());

        $response = $this->post('/api/v1/admin/downloads', [
            'title' => 'Tier Locked File',
            'visibility' => 'tier_specific',
            'is_active' => true,
            'file' => UploadedFile::fake()->create('tier.pdf', 100, 'application/pdf'),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['allowed_tier_ids']);
    }
}
