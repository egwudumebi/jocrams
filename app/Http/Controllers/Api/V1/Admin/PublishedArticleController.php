<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePublishedArticleRequest;
use App\Http\Requests\Admin\UpdatePublishedArticleRequest;
use App\Models\PublishedArticle;
use App\Services\Journal\PublishedArticleService;
use App\Support\Journal\PublishedArticlePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublishedArticleController extends Controller
{
    public function __construct(private readonly PublishedArticleService $service) {}

    public function index(Request $request): JsonResponse
    {
        $query = PublishedArticle::query()
            ->latest('updated_at');

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $paginator = $query->paginate((int) $request->integer('per_page', 20));

        return response()->json([
            'data' => collect($paginator->items())->map(
                fn (PublishedArticle $article) => PublishedArticlePresenter::toArray($article, true)
            )->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StorePublishedArticleRequest $request): JsonResponse
    {
        $data = $request->validated();
        unset($data['pdf']);

        $article = $this->service->create($data, $request->file('pdf'));

        return response()->json([
            'data' => PublishedArticlePresenter::toArray($article, true),
        ], 201);
    }

    public function show(PublishedArticle $article): JsonResponse
    {
        return response()->json([
            'data' => PublishedArticlePresenter::toArray($article, true),
        ]);
    }

    public function update(UpdatePublishedArticleRequest $request, PublishedArticle $article): JsonResponse
    {
        $data = $request->validated();
        unset($data['pdf']);

        $article = $this->service->update($article, $data, $request->file('pdf'));

        return response()->json([
            'data' => PublishedArticlePresenter::toArray($article, true),
        ]);
    }

    public function destroy(PublishedArticle $article): JsonResponse
    {
        $this->service->delete($article);

        return response()->json(['message' => 'Article removed.']);
    }
}
