<?php

namespace App\Support\Settings;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Storage;

class SiteBranding
{
    /** @var array<string, string|null> */
    private static array $cache = [];

    public static function siteName(): string
    {
        return static::setting('site_name') ?: (string) config('app.name', 'JOCRAMS');
    }

    public static function siteTagline(): ?string
    {
        return static::setting('site_tagline');
    }

    public static function parentOrgName(): string
    {
        return static::setting('parent_org_name') ?: (string) config('sicama.parent_org.short_name', 'SICAMA');
    }

    public static function parentOrgFullName(): string
    {
        return static::setting('parent_org_full_name') ?: (string) config('sicama.parent_org.full_name');
    }

    public static function parentOrgMotto(): string
    {
        return static::setting('parent_org_motto') ?: (string) config('sicama.parent_org.motto');
    }

    public static function journalFullName(): string
    {
        return static::setting('journal_full_name') ?: (string) config('sicama.journal.full_name');
    }

    public static function emailLogoUrl(): ?string
    {
        return static::logoUrlFromPath(static::setting('site_logo_path'))
            ?? static::parentOrgLogoUrl();
    }

    public static function siteLogoUrl(): ?string
    {
        return static::logoUrlFromPath(static::setting('site_logo_path'))
            ?? static::parentOrgLogoUrl();
    }

    public static function parentOrgLogoUrl(): ?string
    {
        $path = static::setting('parent_org_logo_path');

        if ($path !== null && $path !== '') {
            $url = static::logoUrlFromPath($path);

            if ($url !== null) {
                return $url;
            }
        }

        return static::defaultLogoUrl();
    }

    public static function defaultLogoUrl(): string
    {
        return url('/images/sicama-logo.png');
    }

    public static function logoDataUri(): ?string
    {
        $path = static::setting('site_logo_path') ?: static::setting('parent_org_logo_path');

        if ($path === null || $path === '' || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($path) ?: 'image/png';
        $contents = Storage::disk('public')->get($path);

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }

    /** @return array<string, mixed> */
    public static function publicPayload(): array
    {
        return [
            'site_name' => static::siteName(),
            'site_tagline' => static::siteTagline(),
            'site_logo_url' => static::siteLogoUrl(),
            'journal_full_name' => static::journalFullName(),
            'parent_org' => [
                'short_name' => static::parentOrgName(),
                'full_name' => static::parentOrgFullName(),
                'motto' => static::parentOrgMotto(),
                'logo_url' => static::parentOrgLogoUrl(),
            ],
        ];
    }

    public static function clearCache(): void
    {
        static::$cache = [];
    }

    private static function logoUrlFromPath(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $publicImage = public_path('images/'.basename($path));

        if (is_file($publicImage)) {
            return url('/images/'.basename($path));
        }

        if (Storage::disk('public')->exists($path) && is_link(public_path('storage'))) {
            return url(Storage::disk('public')->url($path));
        }

        if (Storage::disk('public')->exists($path)) {
            return static::defaultLogoUrl();
        }

        return null;
    }

    private static function setting(string $key): ?string
    {
        if (! array_key_exists($key, static::$cache)) {
            $value = SystemSetting::query()
                ->where('group_name', 'general')
                ->where('setting_key', $key)
                ->value('setting_value');

            static::$cache[$key] = $value !== null ? (string) $value : null;
        }

        return static::$cache[$key];
    }
}
