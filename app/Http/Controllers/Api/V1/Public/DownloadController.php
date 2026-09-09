<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\Download;
use App\Services\Library\SignedUrlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DownloadController extends Controller
{
    public function __construct(private readonly SignedUrlService $signedUrlService) {}

    public function index(): JsonResponse
    {
        $downloads = Download::query()
            ->where('is_active', true)
            ->where('visibility', 'public')
            ->with(['category', 'mediaFile'])
            ->latest('published_at')
            ->paginate(20)
            ->through(function (Download $download): array {
                return array_merge($download->toArray(), [
                    'signed_url' => $this->signedUrlService->generateSignedUrl($download),
                ]);
            });

        return response()->json($downloads);
    }

    public function show(Download $download): JsonResponse
    {
        abort_if(! $download->is_active || $download->visibility->value !== 'public', 404);

        return response()->json(['data' => $download->load('category')]);
    }

    public function serve(Download $download, Request $request)
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Invalid or expired download link.');
        }

        return $this->signedUrlService->stream(
            $download,
            $request->user(),
            $request->ip(),
            $request->userAgent(),
        );
    }
}
