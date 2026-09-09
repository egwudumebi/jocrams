<?php

namespace App\Support\Membership;

use App\Models\Member;
use App\Support\Auth\UserProfilePresenter;

class PublicMemberPresenter
{
    /** @return array<string, mixed> */
    public static function listItem(Member $member): array
    {
        $member->loadMissing(['user.profile', 'tier']);

        $profile = UserProfilePresenter::forUser($member->user);

        return [
            'uuid' => $member->uuid,
            'name' => $member->user->name,
            'membership_number' => $member->membership_number,
            'tier' => [
                'uuid' => $member->tier->uuid,
                'name' => $member->tier->name,
                'slug' => $member->tier->slug,
            ],
            'joined_at' => $member->joined_at,
            'position' => $profile['position'],
            'profile_image' => $profile['profile_image'],
            'city' => $profile['address']['city'] ?? null,
            'state' => $profile['address']['state'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    public static function detail(Member $member): array
    {
        $member->loadMissing(['user.profile', 'tier']);
        $profile = UserProfilePresenter::forUser($member->user);

        return [
            ...self::listItem($member),
            'professional_bio' => $profile['professional_bio'],
            'orcid' => $profile['orcid'],
            'orcid_url' => $profile['orcid_url'],
            'country' => $profile['address']['country'] ?? null,
            'social_links' => $profile['social_links'],
            'achievements' => $profile['achievements'],
        ];
    }
}
