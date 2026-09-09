<?php

namespace App\Support\Seo;

class StructuredData
{
    /**
     * @param  array<string, mixed>  $branding
     * @return array<string, mixed>
     */
    public static function graph(array $branding, string $origin, string $description): array
    {
        $siteName = (string) ($branding['site_name'] ?? 'JOCRAMS');
        $logoUrl = (string) ($branding['site_logo_url'] ?? $origin.'/images/sicama-logo.png');
        $logo = self::logoImageObject($logoUrl, $siteName);

        $organization = [
            '@type' => 'Organization',
            '@id' => $origin.'/#organization',
            'name' => $siteName,
            'url' => $origin,
            'logo' => $logo,
            'image' => $logoUrl,
            'description' => (string) ($branding['site_tagline'] ?? $branding['journal_full_name'] ?? ''),
        ];

        if (! empty($branding['parent_org']['full_name'])) {
            $organization['parentOrganization'] = [
                '@type' => 'Organization',
                'name' => (string) $branding['parent_org']['full_name'],
                'alternateName' => (string) ($branding['parent_org']['short_name'] ?? ''),
                'url' => $origin.'/sicama',
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                $organization,
                [
                    '@type' => 'WebSite',
                    '@id' => $origin.'/#website',
                    'url' => $origin,
                    'name' => $siteName,
                    'description' => $description,
                    'publisher' => ['@id' => $origin.'/#organization'],
                    'inLanguage' => 'en-NG',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function logoImageObject(string $url, string $caption = 'Organization logo'): array
    {
        $dimensions = self::logoDimensions($url);

        return [
            '@type' => 'ImageObject',
            '@id' => $url.'#logo',
            'url' => $url,
            'contentUrl' => $url,
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'caption' => $caption,
        ];
    }

    /**
     * @return array{width: int, height: int, mime: string}
     */
    public static function logoDimensions(string $url): array
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (is_string($path) && $path !== '') {
            $localPath = public_path(ltrim($path, '/'));

            if (is_file($localPath)) {
                $size = @getimagesize($localPath);

                if ($size !== false) {
                    return [
                        'width' => (int) $size[0],
                        'height' => (int) $size[1],
                        'mime' => (string) ($size['mime'] ?? 'image/png'),
                    ];
                }
            }
        }

        return [
            'width' => 1024,
            'height' => 1024,
            'mime' => 'image/png',
        ];
    }
}
