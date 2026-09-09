<?php

namespace App\Services\Membership;

use App\Enums\Visibility;
use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaUploadService
{
    public function store(
        UploadedFile $file,
        string $collection,
        User $uploadedBy,
        Visibility $visibility = Visibility::Private,
        string $disk = 'local',
    ): MediaFile {
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs("{$collection}/{$uploadedBy->uuid}", $filename, $disk);

        return MediaFile::query()->create([
            'uploaded_by' => $uploadedBy->id,
            'disk' => $disk,
            'path' => $path,
            'filename' => $filename,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize(),
            'collection' => $collection,
            'visibility' => $visibility,
        ]);
    }

    public function delete(MediaFile $mediaFile): void
    {
        Storage::disk($mediaFile->disk)->delete($mediaFile->path);
        $mediaFile->delete();
    }
}
