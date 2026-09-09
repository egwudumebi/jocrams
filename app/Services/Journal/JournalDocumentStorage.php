<?php

namespace App\Services\Journal;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Storage;

class JournalDocumentStorage
{
    public function store(string $path, string $content): void
    {
        Storage::disk('local')->put($path, $content);
    }

    public function read(string $path): string
    {
        return Storage::disk('local')->get($path);
    }
}
