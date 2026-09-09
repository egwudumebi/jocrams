<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Http\Controllers\Controller;
use App\Models\Download;
use App\Services\Library\SignedUrlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DownloadController extends Controller
{
    public function __construct(private readonly SignedUrlService $signedUrlService) {}

    public function index(Request $request): JsonResponse
    {
        $downloads = Download::query()
            ->where('is_active', true)
            ->whereIn('visibility', ['public', 'members_only', 'tier_specific'])
            ->with(['category', 'mediaFile'])
            ->latest('published_at')
            ->paginate(20)
            ->through(function (Download $download) use ($request) {
                $canAccess = $this->signedUrlService->canAccess($request->user(), $download);

                return array_merge($download->toArray(), [
                    'can_access' => $canAccess,
                    'signed_url' => $canAccess ? $this->signedUrlService->generateSignedUrl($download) : null,
                ]);
            });

        return response()->json($downloads);
    }

    public function signedUrl(Download $download, Request $request): JsonResponse
    {
        abort_unless($this->signedUrlService->canAccess($request->user(), $download), 403);

        return response()->json([
            'signed_url' => $this->signedUrlService->generateSignedUrl($download),
            'expires_in_minutes' => config('downloads.signed_url_ttl_minutes', 15),
        ]);
    }
}
