<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_registration_sends_verification_email(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/member/auth/register', [
            'name' => 'New Member',
            'email' => 'new-member@gmail.com',
            'phone' => '+2348000000000',
            'password' => 'Str0ng!Pass_6B9xZq7',
            'password_confirmation' => 'Str0ng!Pass_6B9xZq7',
        ]);

        $response->assertCreated();

        $user = User::query()->where('email', 'new-member@gmail.com')->firstOrFail();

        Notification::assertSentTo(
            $user,
            VerifyEmailNotification::class,
            fn (VerifyEmailNotification $notification): bool => str_contains(
                $notification->toMail($user)->actionUrl,
                '/member/verify-email?'
            )
        );

        $this->assertNull($user->email_verified_at);
    }

    public function test_user_can_request_verification_email(): void
    {
        Notification::fake();
        config(['auth.verification_urls.member' => 'http://localhost/member/verify-email']);

        $user = User::factory()->unverified()->create([
            'email' => 'verify-user@gmail.com',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/member/auth/email/verification-notification');

        $response->assertOk()
            ->assertJsonPath('message', 'Verification email sent.');

        Notification::assertSentTo(
            $user,
            VerifyEmailNotification::class,
            fn (VerifyEmailNotification $notification): bool => str_starts_with(
                $notification->toMail($user)->actionUrl,
                'http://localhost/member/verify-email?'
            )
        );
    }

    public function test_user_can_verify_email_with_valid_signed_url(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'verify-user@gmail.com',
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.member.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        Sanctum::actingAs($user);

        $response = $this->getJson($verificationUrl);

        $response->assertOk()
            ->assertJsonPath('message', 'Email verified.');

        $user->refresh();

        $this->assertNotNull($user->email_verified_at);
    }

    public function test_verified_user_cannot_request_another_verification_email(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'verified-user@gmail.com',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/member/auth/email/verification-notification');

        $response->assertOk()
            ->assertJsonPath('message', 'Email already verified.');

        Notification::assertNothingSent();
    }
}
