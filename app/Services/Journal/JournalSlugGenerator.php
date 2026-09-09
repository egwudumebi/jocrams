<?php

namespace App\Services\Journal;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class JournalSlugGenerator
{
    public static function uniqueForTitle(string $title, ?string $excludeSubmissionUuid = null): string
    {
        $base = Str::slug($title);
        if ($base === '') {
            $base = 'journal';
        }

        $slug = $base;
        $suffix = 2;

        while (self::isTaken($slug, $excludeSubmissionUuid)) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private static function isTaken(string $slug, ?string $excludeSubmissionUuid): bool
    {
        $query = DB::table('journal_submissions')->where('slug', $slug);

        if ($excludeSubmissionUuid !== null && $excludeSubmissionUuid !== '') {
            $query->where('uuid', '!=', $excludeSubmissionUuid);
        }

        return $query->exists();
    }
}
