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

        $version = self::versionForPath($profileImagePath);

        // Prefer the public disk URL so LiteSpeed can serve the file statically.
        // Cache-bust so browsers discard 404s cached during outages / re-links.
        if (Storage::disk('public')->exists($profileImagePath)) {
            return url('/storage/'.ltrim($profileImagePath, '/')).'?v='.$version;
        }

        return url('/api/v1/public/users/'.$userUuid.'/profile-image').'?v='.$version;
    }

    private static function versionForPath(string $path): string
    {
        try {
            $mtime = Storage::disk('public')->lastModified($path);

            return substr(sha1($path.'|'.$mtime), 0, 12);
        } catch (\Throwable) {
            return substr(sha1($path), 0, 12);
        }
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
