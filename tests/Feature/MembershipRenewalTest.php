<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Enums\PaymentGateway;
use App\Models\Member;
use App\Models\MembershipTier;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Membership\MembershipRenewalService;
use App\Services\Payments\PaymentService;
use Database\Seeders\MembershipTierSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MembershipRenewalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(MembershipTierSeeder::class);
        $this->seed(SettingsSeeder::class);
    }

    public function test_active_member_cannot_renew_outside_renewal_window(): void
    {
        $member = $this->createMember(now()->addYear());

        $this->assertFalse(app(MembershipRenewalService::class)->canRenew($member));
    }

    public function test_active_member_can_renew_within_renewal_window(): void
    {
        $member = $this->createMember(now()->addDays(20));

        $this->assertTrue(app(MembershipRenewalService::class)->canRenew($member));
    }

    public function test_member_cannot_initiate_dues_before_renewal_window(): void
    {
        Http::fake([
            'api.paystack.co/*' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/test',
                    'access_code' => 'access_code',
                    'reference' => 'PAYSTACK_TESTREF123456',
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $tier = MembershipTier::query()->first();
        Member::query()->create([
            'user_id' => $user->id,
            'membership_tier_id' => $tier->id,
            'membership_number' => 'MEM-TEST5678',
            'status' => MemberStatus::Active,
            'joined_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/member/payments/dues', [
            'gateway' => 'paystack',
            'idempotency_key' => 'unique-key-123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['member']);
    }

    public function test_member_can_initiate_dues_within_renewal_window(): void
    {
        Http::fake([
            'api.paystack.co/*' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/test',
                    'access_code' => 'access_code',
                    'reference' => 'PAYSTACK_TESTREF123456',
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $tier = MembershipTier::query()->first();
        Member::query()->create([
            'user_id' => $user->id,
            'membership_tier_id' => $tier->id,
            'membership_number' => 'MEM-TEST9999',
            'status' => MemberStatus::Active,
            'joined_at' => now()->subMonths(11),
            'expires_at' => now()->addDays(15),
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/member/payments/dues', [
            'gateway' => 'paystack',
            'idempotency_key' => 'unique-key-renewal',
        ])->assertCreated();
    }

    public function test_member_profile_includes_renewal_metadata(): void
    {
        $user = User::factory()->create();
        $tier = MembershipTier::query()->first();
        Member::query()->create([
            'user_id' => $user->id,
            'membership_tier_id' => $tier->id,
            'membership_number' => 'MEM-TESTPROFILE',
            'status' => MemberStatus::Active,
            'joined_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/member/auth/me')
            ->assertOk()
            ->assertJsonPath('user.member.renewal.can_renew', false)
            ->assertJsonStructure([
                'user' => [
                    'member' => [
                        'renewal' => ['can_renew', 'renewal_window_days', 'renewal_opens_at', 'expires_at'],
                    ],
                ],
            ]);
    }

    public function test_public_site_banner_returns_null_when_disabled(): void
    {
        $this->getJson('/api/v1/public/site-banner')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_public_site_banner_returns_message_when_enabled(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['group_name' => 'general', 'setting_key' => 'site_banner_enabled'],
            ['setting_value' => '1', 'is_secret' => false],
        );
        SystemSetting::query()->updateOrCreate(
            ['group_name' => 'general', 'setting_key' => 'site_banner_message'],
            ['setting_value' => 'Conference registration is open.', 'is_secret' => false],
        );

        app(\App\Support\Settings\SiteBanner::class);
        \App\Support\Settings\SiteBanner::clearCache();

        $this->getJson('/api/v1/public/site-banner')
            ->assertOk()
            ->assertJsonPath('data.message', 'Conference registration is open.');
    }

    private function createMember(\Illuminate\Support\Carbon $expiresAt): Member
    {
        $user = User::factory()->create();
        $tier = MembershipTier::query()->firstOrFail();

        return Member::query()->create([
            'user_id' => $user->id,
            'membership_tier_id' => $tier->id,
            'membership_number' => 'MEM-'.random_int(1000, 9999),
            'status' => MemberStatus::Active,
            'joined_at' => now(),
            'expires_at' => $expiresAt,
        ]);
    }
}
