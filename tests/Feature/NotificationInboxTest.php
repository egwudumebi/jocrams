<?php

namespace Tests\Feature;

use App\Enums\NotificationChannel;
use App\Models\NotificationLog;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationInboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_member_can_list_in_app_notifications(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        NotificationLog::query()->create([
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->getKey(),
            'channel' => NotificationChannel::InApp,
            'event' => 'journal.submission.approved',
            'recipient' => $user->email,
            'subject' => 'Manuscript approved',
            'body' => 'Your submission was approved.',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/member/notifications/my');

        $response->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonFragment(['subject' => 'Manuscript approved']);
    }

    public function test_member_can_mark_notification_as_read(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $log = NotificationLog::query()->create([
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->getKey(),
            'channel' => NotificationChannel::InApp,
            'event' => 'journal.submission.received',
            'recipient' => $user->email,
            'subject' => 'Submission received',
            'body' => 'We received your manuscript.',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $this->postJson("/api/v1/member/notifications/{$log->id}/read")
            ->assertOk();

        $this->assertNotNull($log->fresh()->read_at);

        $this->getJson('/api/v1/member/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
    }

    public function test_admin_can_view_in_app_notifications(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@jocrams.test')->first());

        $admin = User::query()->where('email', 'admin@jocrams.test')->first();

        NotificationLog::query()->create([
            'notifiable_type' => $admin->getMorphClass(),
            'notifiable_id' => $admin->getKey(),
            'channel' => NotificationChannel::InApp,
            'event' => 'journal.reviewer.assignment',
            'recipient' => $admin->email,
            'subject' => 'Review assigned',
            'body' => 'A manuscript was assigned.',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $this->getJson('/api/v1/admin/notifications/my')
            ->assertOk()
            ->assertJsonFragment(['subject' => 'Review assigned']);
    }

    public function test_user_cannot_read_another_users_notification(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $log = NotificationLog::query()->create([
            'notifiable_type' => $owner->getMorphClass(),
            'notifiable_id' => $owner->getKey(),
            'channel' => NotificationChannel::InApp,
            'event' => 'journal.submission.rejected',
            'recipient' => $owner->email,
            'subject' => 'Rejected',
            'body' => 'Not accepted.',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        Sanctum::actingAs($other);

        $this->postJson("/api/v1/member/notifications/{$log->id}/read")
            ->assertNotFound();
    }
}
