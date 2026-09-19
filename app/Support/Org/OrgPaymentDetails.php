<?php

namespace App\Support\Org;

use App\Models\SystemSetting;

class OrgPaymentDetails
{
    /** @var list<string> */
    private const PLACEHOLDER_ACCOUNT_NUMBERS = [
        '1234567890',
        '1234667890',
        '0000000000',
    ];

    /** @var list<string> */
    private const PLACEHOLDER_BANK_NAMES = [
        'first bank nigeria',
        'first bank',
        'placeholder bank',
    ];

    /** @var list<string> */
    private const PLACEHOLDER_ACCOUNT_NAMES = [
        'jocrams association',
        'association account',
        'placeholder',
    ];

    /** @return array<string, mixed> */
    public static function publicPayload(): array
    {
        $bank = config('jocrams.bank', []);
        $social = config('jocrams.social', []);

        return [
            'bank' => [
                'bank_name' => self::resolvedBankField('bank_name', $bank['bank_name'] ?? null),
                'account_name' => self::resolvedBankField('account_name', $bank['account_name'] ?? null),
                'account_number' => self::resolvedBankField('account_number', $bank['account_number'] ?? null),
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

    /**
     * Prefer admin settings when present, but never expose known placeholder bank details.
     */
    private static function resolvedBankField(string $key, ?string $fallback): ?string
    {
        $fromSettings = self::setting($key);
        $candidate = $fromSettings ?: $fallback;

        if ($candidate === null || $candidate === '') {
            return $fallback;
        }

        if (self::isPlaceholderBankValue($key, $candidate)) {
            return $fallback;
        }

        return $candidate;
    }

    private static function isPlaceholderBankValue(string $key, string $value): bool
    {
        $normalized = strtolower(trim($value));

        return match ($key) {
            'account_number' => in_array(preg_replace('/\D+/', '', $value) ?? '', self::PLACEHOLDER_ACCOUNT_NUMBERS, true),
            'bank_name' => in_array($normalized, self::PLACEHOLDER_BANK_NAMES, true),
            'account_name' => in_array($normalized, self::PLACEHOLDER_ACCOUNT_NAMES, true),
            default => false,
        };
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
