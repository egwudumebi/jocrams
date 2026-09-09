<?php

namespace App\Support\Journal;

class JournalManuscriptRules
{
    /** @return list<string> */
    public static function document(): array
    {
        return ['required', 'file', 'mimes:docx', 'max:25600'];
    }

    /** @return list<string> */
    public static function optionalDocument(): array
    {
        return ['nullable', 'file', 'mimes:docx', 'max:25600'];
    }

    public static function isDocxFilename(string $filename): bool
    {
        return (bool) preg_match('/\.docx$/i', $filename);
    }
}
