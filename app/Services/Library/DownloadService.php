<?php

namespace App\Services\Library;

use App\Enums\Visibility;
use App\Models\Download;
use App\Models\User;
use App\Services\Membership\MediaUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class DownloadService
{
    public function __construct(private readonly MediaUploadService $mediaUploadService) {}

    /** @param array<string, mixed> $data */
    public function create(User $uploader, array $data, UploadedFile $file): Download
    {
        $media = $this->mediaUploadService->store(
            $file,
            'library',
            $uploader,
            Visibility::Private,
            config('downloads.private_disk', 'local'),
        );

        $visibility = Visibility::tryFrom((string) ($data['visibility'] ?? Visibility::Public->value)) ?? Visibility::Public;
        $isActive = (bool) ($data['is_active'] ?? true);

        return Download::query()->create([
            'media_file_id' => $media->id,
            'category_id' => $data['category_id'] ?? null,
            'title' => $data['title'],
            'slug' => $this->uniqueSlug((string) $data['title']),
            'description' => $data['description'] ?? null,
            'visibility' => $visibility,
            'allowed_tier_ids' => $visibility === Visibility::TierSpecific ? ($data['allowed_tier_ids'] ?? []) : null,
            'is_active' => $isActive,
            'published_at' => $isActive ? now() : null,
        ])->load(['mediaFile', 'category']);
    }

    /** @param array<string, mixed> $data */
    public function update(Download $download, User $uploader, array $data, ?UploadedFile $file = null): Download
    {
        unset($data['file']);

        if ($file) {
            $oldMedia = $download->mediaFile;
            $media = $this->mediaUploadService->store(
                $file,
                'library',
                $uploader,
                Visibility::Private,
                config('downloads.private_disk', 'local'),
            );
            $data['media_file_id'] = $media->id;

            if ($oldMedia) {
                $this->mediaUploadService->delete($oldMedia);
            }
        }

        if (isset($data['visibility'])) {
            $visibility = Visibility::tryFrom((string) $data['visibility']) ?? $download->visibility;

            if ($visibility !== Visibility::TierSpecific) {
                $data['allowed_tier_ids'] = null;
            }
        }

        if (array_key_exists('is_active', $data) && $data['is_active'] && ! $download->published_at) {
            $data['published_at'] = now();
        }

        if (isset($data['title'])) {
            $data['slug'] = $this->uniqueSlug((string) $data['title'], $download->id);
        }

        $download->update($data);

        return $download->fresh(['mediaFile', 'category']);
    }

    public function delete(Download $download): void
    {
        $media = $download->mediaFile;
        $download->delete();

        if ($media && ! Download::query()->withTrashed()->where('media_file_id', $media->id)->exists()) {
            $this->mediaUploadService->delete($media);
        }
    }

    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);
        $slug = $base;

        while (
            Download::query()
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base.'-'.Str::random(6);
        }

        return $slug;
    }
}
