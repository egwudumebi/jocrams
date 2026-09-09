<?php

namespace Tests\Feature;

use Database\Seeders\ContentSeeder;
use Database\Seeders\MembershipTierSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCmsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(MembershipTierSeeder::class);
        $this->seed(ContentSeeder::class);
    }

    public function test_public_news_listing_returns_published_articles(): void
    {
        $this->getJson('/api/v1/public/news')
            ->assertOk()
            ->assertJsonStructure(['data' => [['uuid', 'title', 'excerpt']]]);
    }

    public function test_public_events_listing_returns_published_events(): void
    {
        $this->getJson('/api/v1/public/events')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Annual Conference 2026');
    }

    public function test_public_branches_listing(): void
    {
        $this->getJson('/api/v1/public/branches')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_contact_form_submission(): void
    {
        $this->postJson('/api/v1/public/contact', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'subject' => 'Inquiry',
            'message' => 'Hello, I have a question.',
        ])->assertCreated();
    }
}
