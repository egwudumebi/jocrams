<?php

namespace App\Support\Settings;

class SettingsCatalog
{
    /** @return list<string> */
    public static function groups(): array
    {
        return ['general', 'notifications', 'membership', 'payment', 'security', 'signatory'];
    }

    /** @return list<string> */
    public static function secretKeys(string $group): array
    {
        return match ($group) {
            'payment' => ['secret_key'],
            default => [],
        };
    }

    /** @return list<string> */
    public static function booleanKeys(string $group): array
    {
        return match ($group) {
            'notifications' => [
                'email_notifications',
                'new_member_alerts',
                'payment_notifications',
                'journal_submission_alerts',
                'event_registration_alerts',
                'system_updates',
            ],
            'general' => [
                'site_banner_enabled',
            ],
            'membership' => ['auto_approval'],
            'security' => [
                'require_special_characters',
                'require_numbers',
                'require_uppercase_letters',
                'enable_two_factor_authentication',
            ],
            default => [],
        };
    }
}
