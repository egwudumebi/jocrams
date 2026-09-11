<?php

namespace Tests\Feature;

use App\Enums\JournalCallStatus;
use App\Models\JournalCallForPapers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicOpenJournalCallsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_list_open_calls_for_papers(): void
    {
        $admin = User::factory()->create();

        JournalCallForPapers::query()->create([
            'created_by' => $admin->id,
            'title' => 'Special Issue on Digital Media',
            'slug' => 'special-issue-digital-media',
            'excerpt' => 'Submit your research on digital communication.',
            'status' => JournalCallStatus::Open,
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addMonth(),
        ]);

        $this->getJson('/api/v1/public/journal/calls/open')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Special Issue on Digital Media')
            ->assertJsonPath('data.0.excerpt', 'Submit your research on digital communication.');
    }
}
