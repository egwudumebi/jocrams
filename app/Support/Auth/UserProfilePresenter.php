<?php

namespace App\Support\Auth;

use App\Models\User;
use App\Models\UserProfile;

class UserProfilePresenter
{
    /** @return array<string, mixed> */
    public static function forUser(User $user, ?UserProfile $profile = null): array
    {
        $profile ??= $user->relationLoaded('profile') ? $user->profile : null;

        if (! $profile) {
            return [
                'profile_image' => null,
                'position' => null,
                'professional_bio' => null,
                'orcid' => null,
                'orcid_url' => null,
                'address' => [
                    'country' => null,
                    'state' => null,
                    'city' => null,
                    'street' => null,
                ],
                'social_links' => [],
                'achievements' => [],
            ];
        }

        return [
            'profile_image' => ProfileImageUrl::forUser((string) $user->uuid, $profile->profile_image_path),
            'position' => $profile->position,
            'professional_bio' => $profile->professional_bio,
            'orcid' => $profile->orcid,
            'orcid_url' => Orcid::profileUrl($profile->orcid),
            'address' => [
                'country' => $profile->country,
                'state' => $profile->state,
                'city' => $profile->city,
                'street' => $profile->street,
            ],
            'social_links' => $profile->social_links ?? [],
            'achievements' => $profile->achievements ?? [],
        ];
    }
}
