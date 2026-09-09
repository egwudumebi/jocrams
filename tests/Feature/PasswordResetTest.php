<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_user_can_request_password_reset_link(): void
    {
        Notification::fake();
        config(['app.url' => 'http://localhost', 'auth.password_reset_urls.member' => '/member/reset-password']);

        $user = User::factory()->create([
            'email' => 'password-reset-user@gmail.com',
        ]);

        $response = $this->postJson('/api/v1/auth/password/forgot', [
            'email' => $user->email,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'If an account exists for that email, a password reset link has been sent.');

        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            fn (ResetPasswordNotification $notification): bool => str_starts_with(
                $notification->toMail($user)->actionUrl,
                'http://localhost/member/reset-password?'
            )
        );

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $user->email,
        ]);
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'password-reset-user@gmail.com',
            'password' => 'OldStr0ng!Pass',
            'has_changed_password' => false,
        ]);

        /** @var PasswordBroker $broker */
        $broker = Password::broker();
        $token = $broker->createToken($user);

        $response = $this->postJson('/api/v1/auth/password/reset', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'NewStr0ng!Pass_6B9xZq7',
            'password_confirmation' => 'NewStr0ng!Pass_6B9xZq7',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Password reset successfully.');

        $user->refresh();

        $this->assertTrue(Hash::check('NewStr0ng!Pass_6B9xZq7', (string) $user->password));
        $this->assertTrue((bool) $user->has_changed_password);
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $user->email,
        ]);
    }
}
