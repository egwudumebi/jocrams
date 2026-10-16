<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Enums\JournalIssueStatus;
use App\Http\Controllers\Controller;
use App\Models\JournalIssue;
use App\Models\PublishedArticle;
use App\Support\Journal\PublishedArticlePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublishedArticleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PublishedArticle::query()
            ->published()
            ->orderByDesc('published_at')
            ->orderBy('sort_order')
            ->orderBy('title');

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('keywords', 'like', "%{$search}%")
                    ->orWhere('abstract', 'like', "%{$search}%")
                    ->orWhere('authors', 'like', "%{$search}%");
            });
        }

        if ($request->filled('volume')) {
            $query->where('volume_number', (int) $request->input('volume'));
        }

        if ($request->filled('issue')) {
            $query->where('issue_number', (int) $request->input('issue'));
        }

        $paginator = $query->paginate((int) $request->integer('per_page', 20));

        return response()->json([
            'data' => collect($paginator->items())->map(
                fn (PublishedArticle $article) => PublishedArticlePresenter::toArray($article)
            )->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $article = PublishedArticle::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json([
            'data' => PublishedArticlePresenter::toArray($article),
        ]);
    }

    public function currentIssue(): JsonResponse
    {
        $issue = JournalIssue::query()
            ->with('volume')
            ->where('is_current', true)
            ->where('status', JournalIssueStatus::Published)
            ->first();

        if (! $issue) {
            $issue = JournalIssue::query()
                ->with('volume')
                ->where('status', JournalIssueStatus::Published)
                ->whereHas('publishedArticles', fn ($q) => $q->published())
                ->latest('published_at')
                ->first();
        }

        $articlesQuery = PublishedArticle::query()->published();

        if ($issue) {
            $articlesQuery->where(function ($q) use ($issue): void {
                $q->where('journal_issue_id', $issue->id);

                if ($issue->volume) {
                    $q->orWhere(function ($inner) use ($issue): void {
                        $inner->where('volume_number', $issue->volume->volume_number)
                            ->where('issue_number', $issue->issue_number);
                    });
                }
            });
        } else {
            $latest = PublishedArticle::query()
                ->published()
                ->whereNotNull('volume_number')
                ->orderByDesc('volume_number')
                ->orderByDesc('issue_number')
                ->first();

            if ($latest) {
                $articlesQuery
                    ->where('volume_number', $latest->volume_number)
                    ->where('issue_number', $latest->issue_number);
            }
        }

        $articles = $articlesQuery
            ->orderBy('sort_order')
            ->orderBy('page_start')
            ->orderBy('title')
            ->get()
            ->map(fn (PublishedArticle $article) => PublishedArticlePresenter::toArray($article))
            ->values();

        return response()->json([
            'data' => [
                'issue' => $issue ? [
                    'uuid' => $issue->uuid,
                    'title' => $issue->title,
                    'issue_number' => $issue->issue_number,
                    'volume_number' => $issue->volume?->volume_number,
                    'year' => $issue->volume?->year,
                    'label' => $issue->label(),
                    'published_at' => optional($issue->published_at)?->toIso8601String(),
                ] : (
                    $articles->isNotEmpty()
                        ? [
                            'uuid' => null,
                            'title' => 'Current Issue',
                            'issue_number' => $articles->first()['issue_number'],
                            'volume_number' => $articles->first()['volume_number'],
                            'year' => null,
                            'label' => $articles->first()['volume_issue_label'],
                            'published_at' => null,
                        ]
                        : null
                ),
                'articles' => $articles,
            ],
        ]);
    }
}
