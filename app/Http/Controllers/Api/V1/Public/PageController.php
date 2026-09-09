<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\JsonResponse;

class PageController extends Controller
{
    public function index(): JsonResponse
    {
        $pages = Page::query()
            ->published()
            ->orderBy('sort_order')
            ->orderBy('title')
            ->paginate(20);

        return response()->json($pages);
    }

    public function show(Page $page): JsonResponse
    {
        abort_unless(
            $page->status === 'published' && $page->visibility === 'public',
            404,
        );

        return response()->json(['data' => $page]);
    }
}
