<?php

namespace Tests\Feature\Admin;

use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\MembershipTier;
use App\Models\User;
use App\Notifications\MembershipApprovedNotification;
use Database\Seeders\MembershipTierSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(MembershipTierSeeder::class);
        Storage::fake('local');
    }

    public function test_admin_can_add_member_manually(): void
    {
        Notification::fake();

        Sanctum::actingAs(User::query()->where('email', 'admin@jocrams.test')->first());

        $tier = MembershipTier::query()->first();

        $response = $this->postJson('/api/v1/admin/members', [
            'name' => 'Manual Member',
            'email' => 'manual@example.com',
            'phone' => '+2348011111111',
            'membership_tier_id' => $tier->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('password_reset_sent', true)
            ->assertJsonStructure(['data' => ['uuid', 'membership_number', 'status']]);

        $this->assertDatabaseHas('members', [
            'status' => MemberStatus::Active->value,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'manual@example.com',
        ]);

        $manualUser = User::query()->where('email', 'manual@example.com')->first();
        $this->assertNotNull($manualUser?->email_verified_at);

        Notification::assertSentTo(
            User::query()->where('email', 'manual@example.com')->first(),
            MembershipApprovedNotification::class,
        );
    }

    public function test_admin_can_deactivate_and_reactivate_member(): void
    {
        $admin = User::query()->where('email', 'admin@jocrams.test')->first();
        $user = User::factory()->create();
        $tier = MembershipTier::query()->first();

        $member = Member::query()->create([
            'user_id' => $user->id,
            'membership_tier_id' => $tier->id,
            'membership_number' => 'MEM-TEST0001',
            'status' => MemberStatus::Active,
            'joined_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/members/{$member->uuid}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.status', MemberStatus::Suspended->value);

        $this->postJson("/api/v1/admin/members/{$member->uuid}/reactivate")
            ->assertOk()
            ->assertJsonPath('data.status', MemberStatus::Active->value);
    }

    public function test_add_member_rejects_existing_member_email(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@jocrams.test')->first());

        $user = User::factory()->create(['email' => 'existing@example.com']);
        $tier = MembershipTier::query()->first();

        Member::query()->create([
            'user_id' => $user->id,
            'membership_tier_id' => $tier->id,
            'membership_number' => 'MEM-EXIST001',
            'status' => MemberStatus::Active,
            'joined_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $this->postJson('/api/v1/admin/members', [
            'name' => 'Duplicate',
            'email' => 'existing@example.com',
            'membership_tier_id' => $tier->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }
}
