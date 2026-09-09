<?php

namespace App\Services\Journal;

class JournalSubmissionUrls
{
    public static function document(string $submissionUuid, ?string $slug = null): string
    {
        $key = $slug !== null && $slug !== '' ? $slug : $submissionUuid;

        return url('/api/v1/journal/submissions/'.$key.'/document');
    }
}
