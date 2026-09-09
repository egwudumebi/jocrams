<?php

namespace App\Services\Library;

use App\Enums\Visibility;
use App\Models\Download;
use App\Models\DownloadLog;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SignedUrlService
{
    public function canAccess(?User $user, Download $download): bool
    {
        if (! $download->is_active) {
            return false;
        }

        return match ($download->visibility) {
            Visibility::Public => true,
            Visibility::MembersOnly => $user?->isActiveMember() ?? false,
            Visibility::TierSpecific => $this->canAccessTierSpecific($user, $download),
            default => false,
        };
    }

    public function generateSignedUrl(Download $download): string
    {
        $ttl = config('downloads.signed_url_ttl_minutes', 15);

        return URL::temporarySignedRoute(
            'downloads.serve',
            now()->addMinutes($ttl),
            ['download' => $download->uuid],
        );
    }

    public function stream(Download $download, ?User $user, ?string $ip, ?string $userAgent): StreamedResponse
    {
        $media = $download->mediaFile;

        DownloadLog::query()->create([
            'download_id' => $download->id,
            'user_id' => $user?->id,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);

        $download->increment('download_count');

        return Storage::disk($media->disk)->download(
            $media->path,
            $media->original_filename,
        );
    }

    private function canAccessTierSpecific(?User $user, Download $download): bool
    {
        if (! $user?->isActiveMember()) {
            return false;
        }

        $allowed = $download->allowed_tier_ids ?? [];

        if ($allowed === []) {
            return false;
        }

        return in_array($user->member->membership_tier_id, $allowed, true);
    }
}
