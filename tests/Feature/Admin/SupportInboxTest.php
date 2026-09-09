<?php

namespace Tests\Feature\Admin;

use App\Enums\SupportMessageSource;
use App\Enums\SupportMessageStatus;
use App\Models\ContactInquiry;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupportInboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_public_contact_submission_is_stored(): void
    {
        $response = $this->postJson('/api/v1/public/contact', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'subject' => 'Membership question',
            'message' => 'How do I renew my membership?',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['message', 'message_id']);

        $this->assertDatabaseHas('contact_inquiries', [
            'email' => 'jane@example.com',
            'source' => SupportMessageSource::Contact->value,
            'status' => SupportMessageStatus::New->value,
        ]);
    }

    public function test_admin_can_list_respond_and_resolve_support_messages(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@jocrams.test')->first());

        $inquiry = ContactInquiry::query()->create([
            'name' => 'John Smith',
            'email' => 'john@example.com',
            'subject' => 'Event registration',
            'message' => 'I cannot register for the upcoming event.',
            'source' => SupportMessageSource::Contact,
            'status' => SupportMessageStatus::New,
        ]);

        $this->getJson('/api/v1/admin/support/messages')
            ->assertOk()
            ->assertJsonPath('stats.new', 1)
            ->assertJsonFragment(['subject' => 'Event registration']);

        $this->postJson("/api/v1/admin/support/messages/{$inquiry->uuid}/respond", [
            'response' => 'We have reset your registration link.',
        ])->assertOk()
            ->assertJsonPath('data.status', SupportMessageStatus::Responded->value)
            ->assertJsonPath('data.last_response', 'We have reset your registration link.');

        $this->postJson("/api/v1/admin/support/messages/{$inquiry->uuid}/resolve")
            ->assertOk()
            ->assertJsonPath('data.status', SupportMessageStatus::Resolved->value);
    }

    public function test_member_can_create_and_view_own_support_ticket(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $create = $this->postJson('/api/v1/member/support/messages', [
            'name' => $user->name,
            'email' => $user->email,
            'subject' => 'Credential issue',
            'message' => 'My digital credential will not download.',
        ])->assertCreated();

        $uuid = $create->json('message_id');

        $this->getJson('/api/v1/member/support/messages')
            ->assertOk()
            ->assertJsonFragment(['subject' => 'Credential issue']);

        $this->getJson("/api/v1/member/support/messages/{$uuid}")
            ->assertOk()
            ->assertJsonPath('data.user_id', $user->id);

        $other = User::factory()->create();
        Sanctum::actingAs($other);

        $this->getJson("/api/v1/member/support/messages/{$uuid}")
            ->assertNotFound();
    }
}
