<?php

namespace App\Support\Sicama;

use App\Support\Settings\SiteBranding;

class SicamaProfile
{
    /** @return array<string, mixed> */
    public static function publicPayload(): array
    {
        $config = config('sicama');

        return [
            'parent_org' => [
                'short_name' => SiteBranding::parentOrgName(),
                'full_name' => SiteBranding::parentOrgFullName(),
                'motto' => SiteBranding::parentOrgMotto(),
                'logo_url' => SiteBranding::parentOrgLogoUrl(),
            ],
            'journal' => [
                'short_name' => SiteBranding::siteName(),
                'full_name' => SiteBranding::journalFullName(),
                'tagline' => SiteBranding::siteTagline(),
                'description' => $config['journal']['description'] ?? null,
            ],
            'bot_resolution' => $config['bot_resolution'] ?? [],
            'interim_exco' => $config['interim_exco'] ?? [],
        ];
    }
}
