<?php

namespace App\Support\Credentials;

class CardAsset
{
    public static function dataUri(string $absolutePath): ?string
    {
        if (! is_file($absolutePath)) {
            return null;
        }

        $mime = mime_content_type($absolutePath) ?: 'image/png';
        $contents = file_get_contents($absolutePath);

        if ($contents === false) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }

    public static function publicImageDataUri(string $relativePublicPath): ?string
    {
        return static::dataUri(public_path(ltrim($relativePublicPath, '/')));
    }

    public static function ordinalDayMonthYear(\DateTimeInterface $date): string
    {
        $day = (int) $date->format('j');
        $suffix = match (true) {
            $day % 100 >= 11 && $day % 100 <= 13 => 'th',
            $day % 10 === 1 => 'st',
            $day % 10 === 2 => 'nd',
            $day % 10 === 3 => 'rd',
            default => 'th',
        };

        return $day.$suffix.' '.$date->format('M Y');
    }

    public static function ordinalDay(int $day): string
    {
        $suffix = match (true) {
            $day % 100 >= 11 && $day % 100 <= 13 => 'th',
            $day % 10 === 1 => 'st',
            $day % 10 === 2 => 'nd',
            $day % 10 === 3 => 'rd',
            default => 'th',
        };

        return $day.$suffix;
    }

    /** e.g. "31st day of August, 2026" */
    public static function datedThisDayLabel(\DateTimeInterface $date): string
    {
        return static::ordinalDay((int) $date->format('j')).' day of '.$date->format('F, Y');
    }

    /**
     * Certificate badge layout matching the official sample seal
     * (SICAMA/YYYY-YYYY/NNNN on two lines).
     */
    public static function certificateBadgeNumberHtml(string $membershipNumber): string
    {
        $normalized = trim($membershipNumber);

        if (preg_match('/^(SICAMA|SEC)\s+(\d{4})-(\d{2})\/(\d+)$/i', $normalized, $m)) {
            $label = strtoupper($m[1]);
            $start = $m[2];
            $end = substr($start, 0, 2).$m[3];
            $seq = $m[4];

            return e("{$label}/{$start}-").'<br>'.e("{$end}/{$seq}");
        }

        if (preg_match('/^(SICAMA|SEC)\/(\d{4})-(\d{4})\/(\d+)$/i', $normalized, $m)) {
            return e(strtoupper($m[1]).'/'.$m[2].'-').'<br>'.e($m[3].'/'.$m[4]);
        }

        return e($normalized);
    }

    /** e.g. "2026 - 31st December 2027" */
    public static function membershipPeriodLabel(?\DateTimeInterface $issuedAt, ?\DateTimeInterface $expiresAt): string
    {
        $startYear = $issuedAt?->format('Y') ?? now()->format('Y');

        if ($expiresAt) {
            return $startYear.' - '.static::ordinalDay((int) $expiresAt->format('j')).' '.$expiresAt->format('F Y');
        }

        return $startYear.' - 31st December '.(((int) $startYear) + 1);
    }
}
