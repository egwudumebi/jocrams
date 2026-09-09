<?php

namespace App\Services\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function registerMember(array $data): User
    {
        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'status' => UserStatus::Active,
        ]);

        $user->sendEmailVerificationNotification('member');

        return $user;
    }

    public function login(string $email, string $password, string $tokenName): array
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                'email' => ['Your account is not active.'],
            ]);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $token = $user->createToken($tokenName)->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    public function loginAdmin(string $email, string $password): array
    {
        $result = $this->login($email, $password, 'admin-token');

        if (! $result['user']->isAdmin()) {
            $result['user']->tokens()->where('name', 'admin-token')->delete();

            throw ValidationException::withMessages([
                'email' => ['You do not have admin access.'],
            ]);
        }

        return $result;
    }

    public function loginMember(string $email, string $password): array
    {
        $result = $this->login($email, $password, 'member-token');

        if ($result['user']->isAdmin()) {
            $result['user']->tokens()->where('name', 'member-token')->delete();

            throw ValidationException::withMessages([
                'email' => ['This is an admin account. Please sign in at the admin portal.'],
            ]);
        }

        return $result;
    }

    public function logout(User $user, ?string $tokenName = null): void
    {
        if ($tokenName) {
            $user->tokens()->where('name', $tokenName)->delete();

            return;
        }

        $user->currentAccessToken()?->delete();
    }
}
