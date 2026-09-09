<?php

namespace App\Support\Settings;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

class SiteBanner
{
    /** @return array<string, mixed>|null */
    public static function publicPayload(): ?array
    {
        $data = Cache::remember('site.banner.public', 300, function (): ?array {
            $settings = SystemSetting::query()
                ->where('group_name', 'general')
                ->whereIn('setting_key', [
                    'site_banner_enabled',
                    'site_banner_message',
                    'site_banner_link',
                    'site_banner_link_label',
                    'site_banner_style',
                ])
                ->pluck('setting_value', 'setting_key');

            $enabled = filter_var($settings->get('site_banner_enabled', '0'), FILTER_VALIDATE_BOOLEAN);
            $message = trim((string) $settings->get('site_banner_message', ''));

            if (! $enabled || $message === '') {
                return null;
            }

            $link = trim((string) $settings->get('site_banner_link', ''));
            $style = (string) ($settings->get('site_banner_style', 'info') ?: 'info');

            if (! in_array($style, ['info', 'warning', 'success', 'announcement'], true)) {
                $style = 'info';
            }

            return [
                'message' => $message,
                'link' => $link !== '' ? $link : null,
                'link_label' => trim((string) ($settings->get('site_banner_link_label', '') ?: 'Learn more')),
                'style' => $style,
            ];
        });

        return $data;
    }

    public static function clearCache(): void
    {
        Cache::forget('site.banner.public');
    }
}
