<?php

namespace App\Support\Events;

use App\Models\Event;
use Illuminate\Support\Str;

class EventSlugGenerator
{
    public static function uniqueForTitle(string $title, ?int $excludeEventId = null): string
    {
        $base = Str::slug($title);
        if ($base === '') {
            $base = 'event';
        }

        $slug = $base;
        $suffix = 2;

        while (self::isTaken($slug, $excludeEventId)) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private static function isTaken(string $slug, ?int $excludeEventId): bool
    {
        $query = Event::query()->where('slug', $slug);

        if ($excludeEventId !== null) {
            $query->where('id', '!=', $excludeEventId);
        }

        return $query->exists();
    }
}
