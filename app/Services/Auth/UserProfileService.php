<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\UserProfile;
use App\Support\Auth\ProfileImageUrl;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UserProfileService
{
    /** @param array<string, mixed> $data */
    public function update(User $user, array $data): UserProfile
    {
        return UserProfile::query()->updateOrCreate(
            ['user_id' => (string) $user->uuid],
            [
                'position' => $data['position'] ?? null,
                'professional_bio' => $data['professional_bio'] ?? null,
                'orcid' => $data['orcid'] ?? null,
                'country' => $data['country'] ?? null,
                'state' => $data['state'] ?? null,
                'city' => $data['city'] ?? null,
                'street' => $data['street'] ?? null,
                'social_links' => $data['social_links'] ?? null,
                'achievements' => $data['achievements'] ?? null,
            ],
        );
    }

    public function uploadImage(User $user, UploadedFile $file): string
    {
        $existing = UserProfile::query()->where('user_id', (string) $user->uuid)->first();
        $this->deleteStoredImage($existing?->profile_image_path);

        $path = Storage::disk('public')->putFile('profile-images/'.$user->uuid, $file);

        UserProfile::query()->updateOrCreate(
            ['user_id' => (string) $user->uuid],
            ['profile_image_path' => $path],
        );

        return (string) ProfileImageUrl::forUser((string) $user->uuid, $path);
    }

    public function removeImage(User $user): void
    {
        $profile = UserProfile::query()->where('user_id', (string) $user->uuid)->first();

        if (! $profile?->profile_image_path) {
            return;
        }

        $this->deleteStoredImage($profile->profile_image_path);

        $profile->update(['profile_image_path' => null]);
    }

    private function deleteStoredImage(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
