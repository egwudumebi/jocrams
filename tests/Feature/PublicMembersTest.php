<?php

namespace Tests\Feature;

use App\Enums\CredentialType;
use App\Enums\MemberStatus;
use App\Models\DigitalCredential;
use App\Models\Member;
use App\Models\MembershipTier;
use App\Models\User;
use Database\Seeders\MembershipTierSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PublicMembersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(MembershipTierSeeder::class);
    }

    public function test_public_directory_lists_active_members_only(): void
    {
        $tier = MembershipTier::query()->first();
        $activeUser = User::factory()->create(['name' => 'Active Member']);
        $suspendedUser = User::factory()->create(['name' => 'Suspended Member']);

        $active = Member::query()->create([
            'user_id' => $activeUser->id,
            'membership_tier_id' => $tier->id,
            'membership_number' => 'MEM-ACTIVE01',
            'status' => MemberStatus::Active,
            'joined_at' => now()->subMonth(),
            'expires_at' => now()->addYear(),
        ]);

        Member::query()->create([
            'user_id' => $suspendedUser->id,
            'membership_tier_id' => $tier->id,
            'membership_number' => 'MEM-SUSP001',
            'status' => MemberStatus::Suspended,
            'joined_at' => now()->subMonth(),
            'expires_at' => now()->addYear(),
        ]);

        $response = $this->getJson('/api/v1/public/members');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $active->uuid)
            ->assertJsonPath('data.0.name', 'Active Member')
            ->assertJsonMissing(['email']);
    }

    public function test_public_member_detail_hides_email(): void
    {
        $tier = MembershipTier::query()->first();
        $user = User::factory()->create([
            'name' => 'Public Profile',
            'email' => 'secret@example.com',
        ]);

        $member = Member::query()->create([
            'user_id' => $user->id,
            'membership_tier_id' => $tier->id,
            'membership_number' => 'MEM-PUBLIC1',
            'status' => MemberStatus::Active,
            'joined_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $response = $this->getJson("/api/v1/public/members/{$member->uuid}");

        $response->assertOk()
            ->assertJsonPath('data.name', 'Public Profile')
            ->assertJsonMissing(['email']);

        $this->assertStringNotContainsString('secret@example.com', $response->getContent());
    }

    public function test_suspended_member_not_found_in_public_directory(): void
    {
        $tier = MembershipTier::query()->first();
        $user = User::factory()->create();

        $member = Member::query()->create([
            'user_id' => $user->id,
            'membership_tier_id' => $tier->id,
            'membership_number' => 'MEM-HIDDEN1',
            'status' => MemberStatus::Suspended,
            'joined_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $this->getJson("/api/v1/public/members/{$member->uuid}")
            ->assertNotFound();
    }

    public function test_active_member_can_fetch_membership_card_summary(): void
    {
        $tier = MembershipTier::query()->first();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $member = Member::query()->create([
            'user_id' => $user->id,
            'membership_tier_id' => $tier->id,
            'membership_number' => 'MEM-CARD001',
            'status' => MemberStatus::Active,
            'joined_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        DigitalCredential::query()->create([
            'member_id' => $member->id,
            'type' => CredentialType::MembershipCard,
            'title' => 'Membership Card',
            'template_key' => 'membership_card',
            'verification_token' => 'test-token-123',
            'issued_at' => now(),
            'expires_at' => $member->expires_at,
            'metadata' => ['verify_url' => url('/api/v1/public/verify/test-token-123')],
        ]);

        $this->getJson('/api/v1/member/membership-card')
            ->assertOk()
            ->assertJsonPath('membership_number', 'MEM-CARD001')
            ->assertJsonPath('tier', $tier->name)
            ->assertJsonPath('status', 'active');
    }
}
