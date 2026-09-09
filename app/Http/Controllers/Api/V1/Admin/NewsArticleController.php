<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsArticle;
use App\Support\Html\HtmlSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NewsArticleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = NewsArticle::query()->with(['category', 'author'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('title', 'like', "%{$search}%");
        }

        return response()->json($query->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string'],
            'body' => ['required', 'string'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'status' => ['nullable', 'in:draft,published,archived'],
            'visibility' => ['nullable', 'in:public,members_only'],
        ]);

        $article = NewsArticle::query()->create([
            ...$data,
            'body' => HtmlSanitizer::clean($data['body']) ?? '',
            'slug' => Str::slug($data['title']).'-'.Str::random(6),
            'author_id' => $request->user()->id,
            'status' => $data['status'] ?? 'draft',
            'visibility' => $data['visibility'] ?? 'public',
            'published_at' => ($data['status'] ?? 'draft') === 'published' ? now() : null,
        ]);

        return response()->json(['data' => $article], 201);
    }

    public function show(NewsArticle $article): JsonResponse
    {
        return response()->json(['data' => $article->load(['category', 'author'])]);
    }

    public function update(Request $request, NewsArticle $article): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string'],
            'body' => ['sometimes', 'string'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'status' => ['sometimes', 'in:draft,published,archived'],
            'visibility' => ['sometimes', 'in:public,members_only'],
        ]);

        if (isset($data['status']) && $data['status'] === 'published' && ! $article->published_at) {
            $data['published_at'] = now();
        }

        if (isset($data['body'])) {
            $data['body'] = HtmlSanitizer::clean($data['body']) ?? '';
        }

        $article->update($data);

        return response()->json(['data' => $article->fresh(['category', 'author'])]);
    }

    public function destroy(NewsArticle $article): JsonResponse
    {
        $article->delete();

        return response()->json(['message' => 'Article deleted.']);
    }
}
