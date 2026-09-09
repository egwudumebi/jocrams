<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Models\Member;
use App\Models\MembershipTier;
use App\Models\User;
use App\Services\Payments\PaymentService;
use Database\Seeders\MembershipTierSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(MembershipTierSeeder::class);
        $this->seed(SettingsSeeder::class);
    }

    public function test_payment_initiation_is_idempotent(): void
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
            'membership_number' => 'MEM-TEST1234',
            'status' => MemberStatus::Active,
            'joined_at' => now(),
            'expires_at' => now()->addDays(15),
        ]);

        $service = app(PaymentService::class);

        $payment1 = $service->initiateDuesPayment($user->fresh(['member.tier']), 'idem-key-001', PaymentGateway::Paystack);
        $payment2 = $service->initiateDuesPayment($user->fresh(['member.tier']), 'idem-key-001', PaymentGateway::Paystack);

        $this->assertSame($payment1->id, $payment2->id);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_member_can_initiate_dues_payment_via_api(): void
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
            'expires_at' => now()->addDays(15),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/member/payments/dues', [
            'gateway' => 'paystack',
            'idempotency_key' => 'unique-key-123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', PaymentStatus::Processing->value)
            ->assertJsonPath('data.purpose', PaymentPurpose::Dues->value);
    }
}
