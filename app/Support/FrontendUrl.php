<?php

namespace App\Support;

class FrontendUrl
{
    public static function to(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return rtrim($path, '/');
        }

        $path = '/'.ltrim($path, '/');

        return rtrim((string) config('app.url'), '/').$path;
    }
}
