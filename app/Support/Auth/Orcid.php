<?php

namespace App\Support\Auth;

class Orcid
{
    public static function normalize(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        if (preg_match('#orcid\.org/([^/\s]+)#i', $value, $matches)) {
            $value = $matches[1];
        }

        $compact = strtoupper(preg_replace('/[^0-9X]/i', '', $value) ?? '');

        if (strlen($compact) !== 16) {
            return strtoupper($value);
        }

        return substr($compact, 0, 4).'-'
            .substr($compact, 4, 4).'-'
            .substr($compact, 8, 4).'-'
            .substr($compact, 12, 4);
    }

    public static function isValid(?string $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return (bool) preg_match('/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/i', $value);
    }

    public static function profileUrl(?string $orcid): ?string
    {
        $normalized = self::normalize($orcid);

        if ($normalized === null || ! self::isValid($normalized)) {
            return null;
        }

        return 'https://orcid.org/'.$normalized;
    }
}
