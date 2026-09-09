<?php

namespace Tests\Feature;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_member_login_is_rate_limited(): void
    {
        RateLimiter::clear('auth');

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $response = $this->postJson('/api/v1/member/auth/login', [
                'email' => 'blocked@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $response->assertStatus(429);
    }

    public function test_contact_form_is_rate_limited(): void
    {
        RateLimiter::clear('contact');

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $response = $this->postJson('/api/v1/public/contact', [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'subject' => 'Hello',
                'message' => 'Testing rate limits.',
            ]);
        }

        $response->assertStatus(429);
    }

    public function test_credential_verification_is_rate_limited(): void
    {
        RateLimiter::clear('verify');

        for ($attempt = 1; $attempt <= 31; $attempt++) {
            $response = $this->getJson('/api/v1/public/verify/invalid-token-'.$attempt);
        }

        $response->assertStatus(429);
    }
}
