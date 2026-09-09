<?php

namespace App\Support\Html;

class HtmlSanitizer
{
    private const ALLOWED_TAGS = '<p><br><strong><b><em><i><u><ul><ol><li><a><h2><h3><h4><blockquote><span>';

    public static function normalize(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        if (! str_contains($html, '&lt;') && ! str_contains($html, '&gt;') && ! str_contains($html, '&amp;lt;')) {
            return $html;
        }

        $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if ($decoded !== $html && (str_contains($decoded, '&lt;') || str_contains($decoded, '&gt;'))) {
            $decoded = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $decoded;
    }

    public static function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $html = self::normalize($html) ?? '';
        $clean = strip_tags($html, self::ALLOWED_TAGS);

        return trim($clean) === '' ? null : $clean;
    }
}
