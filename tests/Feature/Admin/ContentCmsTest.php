<?php

namespace Tests\Feature\Admin;

use App\Models\Page;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContentCmsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('public');
        Storage::fake('local');
    }

    public function test_admin_can_view_content_overview(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@jocrams.test')->first());

        $response = $this->getJson('/api/v1/admin/content/overview');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'counts' => [
                        'news' => ['total', 'published', 'draft'],
                        'pages' => ['total', 'published', 'draft'],
                        'downloads' => ['total', 'active'],
                        'media_catalog',
                    ],
                ],
            ]);
    }

    public function test_admin_can_create_and_publish_page(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@jocrams.test')->first());

        $response = $this->postJson('/api/v1/admin/pages', [
            'title' => 'About Us',
            'body' => '<p>Welcome to our association.</p>',
            'status' => 'published',
            'visibility' => 'public',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'About Us')
            ->assertJsonPath('data.status', 'published');

        $uuid = $response->json('data.uuid');

        $this->getJson("/api/v1/public/pages/{$uuid}")
            ->assertOk()
            ->assertJsonPath('data.title', 'About Us');
    }

    public function test_draft_page_is_hidden_from_public_api(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@jocrams.test')->first());

        $page = Page::query()->create([
            'title' => 'Draft Policy',
            'slug' => 'draft-policy',
            'body' => 'Draft content',
            'status' => 'draft',
            'visibility' => 'public',
        ]);

        $this->getJson("/api/v1/public/pages/{$page->uuid}")
            ->assertNotFound();
    }

    public function test_admin_can_upload_media_and_it_appears_in_public_catalog(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@jocrams.test')->first());

        $upload = $this->post('/api/v1/admin/media-library', [
            'title' => 'Conference Photo',
            'description' => 'Annual conference gallery',
            'file_type' => 'image',
            'file' => UploadedFile::fake()->image('conference.jpg'),
        ]);

        $upload->assertCreated()
            ->assertJsonPath('data.title', 'Conference Photo');

        $uuid = $upload->json('data.uuid');

        $this->getJson('/api/v1/public/media-catalog')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Conference Photo']);

        $this->get("/api/v1/public/media-catalog/{$uuid}/view")
            ->assertOk();

        $this->deleteJson("/api/v1/admin/media-library/{$uuid}")
            ->assertOk();

        $this->assertSoftDeleted('media_files', ['uuid' => $uuid]);
    }

    public function test_admin_can_update_and_delete_page(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@jocrams.test')->first());

        $create = $this->postJson('/api/v1/admin/pages', [
            'title' => 'Terms',
            'body' => 'Terms body',
            'status' => 'draft',
        ])->assertCreated();

        $uuid = $create->json('data.uuid');

        $this->putJson("/api/v1/admin/pages/{$uuid}", [
            'title' => 'Terms of Service',
            'status' => 'published',
        ])->assertOk()
            ->assertJsonPath('data.title', 'Terms of Service');

        $this->deleteJson("/api/v1/admin/pages/{$uuid}")
            ->assertOk();

        $this->assertSoftDeleted('pages', ['uuid' => $uuid]);
    }
}
