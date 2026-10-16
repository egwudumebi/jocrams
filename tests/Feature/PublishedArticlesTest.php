<?php

namespace Tests\Feature;

use App\Enums\PublishedArticleStatus;
use App\Models\PublishedArticle;
use App\Models\User;
use Database\Seeders\CrossrefSampleArticlesSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PublishedArticlesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
    }

    public function test_public_can_list_and_view_published_article_landing_pages(): void
    {
        $this->seed(CrossrefSampleArticlesSeeder::class);

        $this->getJson('/api/v1/public/articles')
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.doi', null);

        $slug = 'digital-media-literacy-and-civic-engagement-in-nigeria';

        $this->getJson("/api/v1/public/articles/{$slug}")
            ->assertOk()
            ->assertJsonPath('data.slug', $slug)
            ->assertJsonPath('data.has_pdf', true)
            ->assertJsonPath('data.pdf_url', "/articles/{$slug}/pdf")
            ->assertJsonStructure([
                'data' => [
                    'publisher',
                    'journal_title',
                    'authors',
                    'abstract',
                    'keywords',
                    'article_type',
                    'received_at',
                    'accepted_at',
                    'published_at',
                    'volume_number',
                    'issue_number',
                    'pages',
                    'citation',
                    'copyright',
                    'license',
                    'landing_url',
                ],
            ]);

        $this->getJson('/api/v1/public/articles/current-issue')
            ->assertOk()
            ->assertJsonCount(3, 'data.articles');

        $this->get("/articles/{$slug}/pdf")->assertOk();
    }

    public function test_admin_can_publish_article_with_pdf(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@jocrams.test')->firstOrFail());

        $pdf = UploadedFile::fake()->create('paper.pdf', 200, 'application/pdf');

        $this->post('/api/v1/admin/published-articles', [
            'title' => 'Open Access Communication Research in Africa',
            'authors' => json_encode([
                [
                    'name' => 'Test Author',
                    'affiliation' => 'University of Calabar',
                    'orcid' => '0000-0001-2345-6789',
                ],
            ]),
            'abstract' => 'An abstract for a newly published JOCRAMS article landing page.',
            'keywords' => 'communication, Africa, open access',
            'article_type' => 'Research Article',
            'received_at' => '2026-01-01',
            'accepted_at' => '2026-02-01',
            'published_at' => '2026-03-01',
            'volume_number' => 1,
            'issue_number' => 2,
            'page_start' => '10',
            'page_end' => '22',
            'status' => 'published',
            'pdf' => $pdf,
        ])->assertCreated()
            ->assertJsonPath('data.slug', 'open-access-communication-research-in-africa')
            ->assertJsonPath('data.has_pdf', true)
            ->assertJsonPath('data.doi', null);

        $this->getJson('/api/v1/public/articles/open-access-communication-research-in-africa')
            ->assertOk()
            ->assertJsonPath('data.title', 'Open Access Communication Research in Africa');
    }

    public function test_crossref_sample_articles_cannot_be_deleted(): void
    {
        $article = PublishedArticle::query()->create([
            'slug' => 'permanent-sample-article',
            'title' => 'Permanent Sample',
            'authors' => [['name' => 'Sample Author', 'affiliation' => 'JOCRAMS', 'orcid' => null]],
            'abstract' => 'Sample abstract for deletion protection.',
            'article_type' => 'Research Article',
            'published_at' => now()->toDateString(),
            'status' => PublishedArticleStatus::Published,
            'is_crossref_sample' => true,
            'copyright' => '© JOCRAMS',
            'license' => 'CC BY 4.0',
        ]);

        Sanctum::actingAs(User::query()->where('email', 'admin@jocrams.test')->firstOrFail());

        $this->deleteJson("/api/v1/admin/published-articles/{$article->uuid}")
            ->assertStatus(422);
    }
}
