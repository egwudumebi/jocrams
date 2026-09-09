<?php

namespace App\Support\Auth;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\Storage;

class ProfileImageUrl
{
    public static function forUser(?string $userUuid, ?string $profileImagePath): ?string
    {
        if ($userUuid === null || $userUuid === '' || $profileImagePath === null || $profileImagePath === '') {
            return null;
        }

        return url('/api/v1/public/users/'.$userUuid.'/profile-image');
    }

    public static function dataUriForUser(User $user): ?string
    {
        $profile = $user->relationLoaded('profile')
            ? $user->profile
            : UserProfile::query()->where('user_id', $user->uuid)->first();

        $path = $profile?->profile_image_path;

        if ($path === null || $path === '' || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($path) ?: 'image/jpeg';

        return 'data:'.$mime.';base64,'.base64_encode((string) Storage::disk('public')->get($path));
    }
}
