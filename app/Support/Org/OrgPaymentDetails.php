<?php

namespace App\Support\Org;

use App\Models\SystemSetting;

class OrgPaymentDetails
{
    /** @return array<string, mixed> */
    public static function publicPayload(): array
    {
        $bank = config('jocrams.bank', []);
        $social = config('jocrams.social', []);

        return [
            'bank' => [
                'bank_name' => self::setting('bank_name') ?: ($bank['bank_name'] ?? null),
                'account_name' => self::setting('account_name') ?: ($bank['account_name'] ?? null),
                'account_number' => self::setting('account_number') ?: ($bank['account_number'] ?? null),
                'evidence_email' => $bank['evidence_email'] ?? config('jocrams.email'),
                'payment_contacts' => $bank['payment_contacts'] ?? [],
            ],
            'fees' => config('jocrams.fees', []),
            'social' => [
                'facebook' => self::setting('social_facebook') ?: ($social['facebook'] ?? ''),
                'linkedin' => self::setting('social_linkedin') ?: ($social['linkedin'] ?? ''),
                'x' => self::setting('social_x') ?: ($social['x'] ?? ''),
                'instagram' => self::setting('social_instagram') ?: ($social['instagram'] ?? ''),
                'youtube' => self::setting('social_youtube') ?: ($social['youtube'] ?? ''),
            ],
            'contact' => [
                'email' => config('jocrams.email'),
                'phones' => config('jocrams.phones', []),
                'website' => config('jocrams.website'),
            ],
            'payment_mode' => 'bank_transfer',
            'payment_mode_label' => 'Direct bank transfer (admin approval)',
        ];
    }

    private static function setting(string $key): ?string
    {
        $value = SystemSetting::query()
            ->where('group_name', 'payment')
            ->where('setting_key', $key)
            ->value('setting_value');

        if ($value === null || $value === '') {
            $value = SystemSetting::query()
                ->where('group_name', 'general')
                ->where('setting_key', $key)
                ->value('setting_value');
        }

        return $value !== null && $value !== '' ? (string) $value : null;
    }
}
