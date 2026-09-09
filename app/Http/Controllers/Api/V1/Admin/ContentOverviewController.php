<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Download;
use App\Models\MediaFile;
use App\Models\NewsArticle;
use App\Models\Page;
use Illuminate\Http\JsonResponse;

class ContentOverviewController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => [
                'counts' => [
                    'news' => [
                        'total' => NewsArticle::query()->count(),
                        'published' => NewsArticle::query()->where('status', 'published')->count(),
                        'draft' => NewsArticle::query()->where('status', 'draft')->count(),
                    ],
                    'pages' => [
                        'total' => Page::query()->count(),
                        'published' => Page::query()->where('status', 'published')->count(),
                        'draft' => Page::query()->where('status', 'draft')->count(),
                    ],
                    'downloads' => [
                        'total' => Download::query()->count(),
                        'active' => Download::query()->where('is_active', true)->count(),
                    ],
                    'media_catalog' => MediaFile::query()
                        ->where('collection', 'media-catalog')
                        ->count(),
                ],
            ],
        ]);
    }
}
