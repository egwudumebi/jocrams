<?php

namespace App\Support\Content;

use App\Models\MediaFile;
use Illuminate\Support\Collection;

class MediaCatalogPresenter
{
    /** @return array<string, mixed> */
    public static function toPublicArray(MediaFile $media): array
    {
        $meta = $media->metadata ?? [];

        return [
            'uuid' => $media->uuid,
            'title' => $meta['title'] ?? $media->original_filename,
            'description' => $meta['description'] ?? null,
            'file_type' => $meta['file_type'] ?? self::inferFileType($media->mime_type),
            'original_filename' => $media->original_filename,
            'mime_type' => $media->mime_type,
            'file_size_bytes' => $media->size,
            'file_size_mb' => $media->size > 0 ? round($media->size / 1048576, 2) : 0,
            'event_date' => $meta['event_date'] ?? null,
            'location' => $meta['location'] ?? null,
            'batch_id' => $meta['batch_id'] ?? $media->uuid,
            'created_at' => $media->created_at?->toIso8601String(),
            'file_url' => url("/api/v1/public/media-catalog/{$media->uuid}/view"),
            'download_url' => url("/api/v1/public/media-catalog/{$media->uuid}/file"),
        ];
    }

    /** @return array<string, mixed> */
    public static function toAdminArray(MediaFile $media): array
    {
        return array_merge(self::toPublicArray($media), [
            'collection' => $media->collection,
            'visibility' => $media->visibility?->value ?? $media->visibility,
            'uploaded_by' => $media->uploaded_by,
        ]);
    }

    /**
     * @param  Collection<int, MediaFile>  $files
     * @return array<string, mixed>
     */
    public static function toBatchArray(Collection $files): array
    {
        $first = $files->first();
        $meta = $first?->metadata ?? [];

        return [
            'batch_id' => $meta['batch_id'] ?? $first?->uuid,
            'title' => $meta['title'] ?? $first?->original_filename,
            'description' => $meta['description'] ?? null,
            'file_type' => $meta['file_type'] ?? self::inferFileType($first?->mime_type),
            'event_date' => $meta['event_date'] ?? null,
            'location' => $meta['location'] ?? null,
            'files' => $files->map(fn (MediaFile $media) => self::toPublicArray($media))->values()->all(),
        ];
    }

    private static function inferFileType(?string $mimeType): string
    {
        return match (true) {
            str_starts_with((string) $mimeType, 'image/') => 'image',
            str_starts_with((string) $mimeType, 'video/') => 'video',
            default => 'document',
        };
    }
}
