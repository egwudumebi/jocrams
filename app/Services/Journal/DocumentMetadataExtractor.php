<?php

namespace App\Services\Journal;

use Illuminate\Http\UploadedFile;

class DocumentMetadataExtractor
{
    /** @return array<string, string> */
    public function extract(UploadedFile $file): array
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());

        $rawText = '';
        if ($ext === 'pdf' && extension_loaded('imagick') && class_exists('Imagick')) {
            try {
                $imagickClass = 'Imagick';
                $imagick = new $imagickClass;
                $imagick->setResolution(150, 150);
                $imagick->readImage($file->getRealPath());
                $rawText = (string) $imagick->getImageProperty('pdf:Text');
            } catch (\Throwable) {
                $rawText = '';
            }
        }

        $rawText = trim($rawText);
        if ($rawText === '') {
            return [];
        }

        $lines = preg_split('/\R/', $rawText) ?: [];
        $lines = array_values(array_filter(array_map('trim', $lines), fn ($x) => $x !== ''));

        $title = $lines[0] ?? null;
        $authors = $lines[1] ?? null;

        $abstract = null;
        $abstractStart = null;
        foreach ($lines as $i => $line) {
            if (preg_match('/^abstract\b\s*:?/i', $line)) {
                $abstractStart = $i;
                break;
            }
        }

        if ($abstractStart !== null) {
            $abstractLines = [];
            $first = preg_replace('/^abstract\b\s*:?/i', '', $lines[$abstractStart]);
            $first = trim((string) $first);
            if ($first !== '') {
                $abstractLines[] = $first;
            }

            for ($j = $abstractStart + 1; $j < count($lines); $j++) {
                if (preg_match('/^(keywords|introduction|background)\b\s*:?/i', $lines[$j])) {
                    break;
                }
                $abstractLines[] = $lines[$j];
            }

            $abstract = trim(implode(' ', $abstractLines));
        }

        $keywords = null;
        foreach ($lines as $line) {
            if (preg_match('/^keywords\b\s*:?/i', $line)) {
                $kw = trim((string) preg_replace('/^keywords\b\s*:?/i', '', $line));
                if ($kw !== '') {
                    $keywords = $kw;
                }
                break;
            }
        }

        return array_filter([
            'title' => $title,
            'author_name' => $authors,
            'abstract' => $abstract,
            'keywords' => $keywords,
        ], fn ($v) => $v !== null && $v !== '');
    }
}
