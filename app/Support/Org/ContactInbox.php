<?php

namespace App\Support\Org;

use App\Models\SystemSetting;

class ContactInbox
{
    /**
     * Official JOCRAMS contact inbox from client docs / config.
     * Prefer admin settings when set; otherwise config('jocrams.email').
     */
    public static function email(): string
    {
        $fromSettings = SystemSetting::query()
            ->where('group_name', 'general')
            ->where('setting_key', 'contact_email')
            ->value('setting_value');

        if (is_string($fromSettings) && filter_var(trim($fromSettings), FILTER_VALIDATE_EMAIL)) {
            return trim($fromSettings);
        }

        return (string) config('jocrams.email', 'jocrams2026@gmail.com');
    }
}
