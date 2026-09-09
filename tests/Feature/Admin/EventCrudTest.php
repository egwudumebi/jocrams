<?php

namespace Tests\Feature\Admin;

use App\Models\Event;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('public');
    }

    public function test_admin_can_create_event(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@jocrams.test')->first());

        $response = $this->postJson('/api/v1/admin/events', [
            'title' => 'Leadership Summit 2026',
            'description' => 'A summit for association leaders.',
            'location' => 'Abuja',
            'starts_at' => now()->addMonth()->toISOString(),
            'ends_at' => now()->addMonth()->addDay()->toISOString(),
            'max_attendees' => 200,
            'fee' => 5000,
            'visibility' => 'public',
            'status' => 'published',
            'sessions' => [
                [
                    'title' => 'Opening Keynote',
                    'starts_at' => now()->addMonth()->toISOString(),
                    'ends_at' => now()->addMonth()->addHours(2)->toISOString(),
                ],
            ],
            'pricing_tiers' => [
                ['category' => 'member', 'fee' => 5000],
                ['category' => 'non_member', 'fee' => 7500],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Leadership Summit 2026')
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.sessions.0.title', 'Opening Keynote')
            ->assertJsonPath('data.pricing_tiers.0.category', 'member');

        $this->assertDatabaseHas('events', [
            'title' => 'Leadership Summit 2026',
            'slug' => 'leadership-summit-2026',
            'status' => 'published',
        ]);
    }

    public function test_admin_can_update_and_delete_event(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@jocrams.test')->first());

        $create = $this->postJson('/api/v1/admin/events', [
            'title' => 'Draft Workshop',
            'starts_at' => now()->addWeeks(2)->toISOString(),
            'status' => 'draft',
            'visibility' => 'public',
        ])->assertCreated();

        $uuid = $create->json('data.uuid');

        $this->putJson("/api/v1/admin/events/{$uuid}", [
            'title' => 'Published Workshop',
            'status' => 'published',
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Published Workshop')
            ->assertJsonPath('data.slug', 'published-workshop');

        $this->deleteJson("/api/v1/admin/events/{$uuid}")
            ->assertOk()
            ->assertJsonPath('message', 'Event deleted.');

        $this->assertSoftDeleted('events', ['uuid' => $uuid]);
    }

    public function test_published_event_appears_on_public_listing(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@jocrams.test')->first());

        $this->postJson('/api/v1/admin/events', [
            'title' => 'Public Meetup',
            'starts_at' => now()->addWeek()->toISOString(),
            'status' => 'published',
            'visibility' => 'public',
        ])->assertCreated();

        $response = $this->getJson('/api/v1/public/events');

        $response->assertOk();

        $titles = collect($response->json('data'))->pluck('title');
        $this->assertTrue($titles->contains('Public Meetup'));
    }

    public function test_published_past_event_appears_when_upcoming_filter_disabled(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@jocrams.test')->first());

        $this->postJson('/api/v1/admin/events', [
            'title' => 'Past Conference',
            'starts_at' => now()->subWeek()->toISOString(),
            'ends_at' => now()->subWeek()->addDay()->toISOString(),
            'status' => 'published',
            'visibility' => 'public',
        ])->assertCreated();

        $this->getJson('/api/v1/public/events?upcoming=1')
            ->assertOk()
            ->assertJsonMissing(['title' => 'Past Conference']);

        $response = $this->getJson('/api/v1/public/events?upcoming=0');

        $response->assertOk();

        $titles = collect($response->json('data'))->pluck('title');
        $this->assertTrue($titles->contains('Past Conference'));
    }

    public function test_members_only_event_is_hidden_from_public_listing(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@jocrams.test')->first());

        $this->postJson('/api/v1/admin/events', [
            'title' => 'Members Retreat',
            'starts_at' => now()->addWeek()->toISOString(),
            'status' => 'published',
            'visibility' => 'members_only',
        ])->assertCreated();

        $titles = collect($this->getJson('/api/v1/public/events?upcoming=0')->json('data'))->pluck('title');

        $this->assertFalse($titles->contains('Members Retreat'));
    }

    public function test_admin_can_upload_event_banner(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@jocrams.test')->first());

        $event = Event::query()->create([
            'organizer_id' => User::query()->where('email', 'admin@jocrams.test')->value('id'),
            'title' => 'Banner Event',
            'slug' => 'banner-event',
            'starts_at' => now()->addWeek(),
            'status' => 'published',
            'visibility' => 'public',
        ]);

        $response = $this->post("/api/v1/admin/events/{$event->uuid}", [
            'banner' => UploadedFile::fake()->image('banner.jpg'),
            '_method' => 'PUT',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['data' => ['banner_url']]);

        $event->refresh();
        $this->assertNotNull($event->featured_image_media_id);

        $this->get("/api/v1/public/events/{$event->uuid}/banner")
            ->assertOk();
    }
}
