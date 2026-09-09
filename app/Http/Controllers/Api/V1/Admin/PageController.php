<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePageRequest;
use App\Http\Requests\Admin\UpdatePageRequest;
use App\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Page::query()->orderBy('sort_order')->latest('updated_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $query->where('title', 'like', '%'.$request->input('search').'%');
        }

        return response()->json($query->paginate(20));
    }

    public function store(StorePageRequest $request): JsonResponse
    {
        $data = $request->validated();

        $page = Page::query()->create([
            ...$data,
            'slug' => $this->uniqueSlug($data['title']),
            'status' => $data['status'] ?? 'draft',
            'visibility' => $data['visibility'] ?? 'public',
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return response()->json(['data' => $page], 201);
    }

    public function show(Page $page): JsonResponse
    {
        return response()->json(['data' => $page]);
    }

    public function update(UpdatePageRequest $request, Page $page): JsonResponse
    {
        $data = $request->validated();

        if (isset($data['title'])) {
            $data['slug'] = $this->uniqueSlug($data['title'], $page->id);
        }

        $page->update($data);

        return response()->json(['data' => $page->fresh()]);
    }

    public function destroy(Page $page): JsonResponse
    {
        $page->delete();

        return response()->json(['message' => 'Page deleted.']);
    }

    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);
        $slug = $base;

        while (
            Page::query()
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base.'-'.Str::random(6);
        }

        return $slug;
    }
}
