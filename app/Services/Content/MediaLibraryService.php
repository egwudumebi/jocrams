<?php

namespace App\Services\Content;

use App\Enums\Visibility;
use App\Models\MediaFile;
use App\Models\User;
use App\Services\Membership\MediaUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class MediaLibraryService
{
    public function __construct(private readonly MediaUploadService $mediaUploadService) {}

    /** @param array<string, mixed> $data */
    public function upload(User $uploader, array $data, UploadedFile $file): MediaFile
    {
        $fileType = (string) ($data['file_type'] ?? $this->inferFileType($file));
        $disk = $fileType === 'image' ? 'public' : config('downloads.private_disk', 'local');

        $media = $this->mediaUploadService->store(
            $file,
            'media-catalog',
            $uploader,
            Visibility::Public,
            $disk,
        );

        $media->update([
            'metadata' => [
                'title' => $data['title'] ?? $file->getClientOriginalName(),
                'description' => $data['description'] ?? null,
                'batch_id' => $data['batch_id'] ?? (string) Str::uuid(),
                'file_type' => $fileType,
                'event_date' => $data['event_date'] ?? null,
                'location' => $data['location'] ?? null,
            ],
        ]);

        return $media->fresh(['uploader']);
    }

    public function delete(MediaFile $media): void
    {
        $this->mediaUploadService->delete($media);
    }

    private function inferFileType(UploadedFile $file): string
    {
        $mime = $file->getMimeType() ?? '';

        return match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            default => 'document',
        };
    }
}
