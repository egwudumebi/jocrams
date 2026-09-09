<?php

namespace Tests\Feature;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class OpsParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        RateLimiter::clear('auth');
    }

    public function test_responses_include_security_headers(): void
    {
        $response = $this->getJson('/api/v1/public/membership-tiers');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_auth_routes_are_rate_limited(): void
    {
        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $response = $this->postJson('/api/v1/member/auth/login', [
                'email' => 'blocked@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $response->assertStatus(429);
    }

    public function test_monitor_health_command_runs_once(): void
    {
        config([
            'monitoring.targets' => [
                'app' => url('/up'),
            ],
        ]);

        $this->artisan('monitor:health', ['--once' => true])
            ->assertSuccessful();
    }
}
