<?php

namespace App\Services\Journal;

use App\Enums\PublishedArticleStatus;
use App\Models\PublishedArticle;
use App\Support\Settings\SiteBranding;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PublishedArticleService
{
    /** @param array<string, mixed> $data */
    public function create(array $data, ?UploadedFile $pdf = null): PublishedArticle
    {
        return DB::transaction(function () use ($data, $pdf) {
            $article = new PublishedArticle($this->normalize($data));
            $article->uuid = (string) Str::uuid();
            $article->slug = $this->uniqueSlug((string) ($data['slug'] ?? $data['title']));
            $article->citation = $article->citation ?: $this->buildCitation($article);
            $article->save();

            if ($pdf) {
                $this->storePdf($article, $pdf);
                $article->citation = $article->citation ?: $this->buildCitation($article);
                $article->save();
            }

            return $article->fresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function update(PublishedArticle $article, array $data, ?UploadedFile $pdf = null): PublishedArticle
    {
        return DB::transaction(function () use ($article, $data, $pdf) {
            $normalized = $this->normalize($data, $article);

            if (isset($data['slug']) && $data['slug'] !== $article->slug) {
                if ($article->is_crossref_sample) {
                    unset($normalized['slug']);
                } else {
                    $normalized['slug'] = $this->uniqueSlug((string) $data['slug'], $article->id);
                }
            }

            $article->fill($normalized);

            if (blank($article->citation) || ($data['regenerate_citation'] ?? false)) {
                $article->citation = $this->buildCitation($article);
            }

            if ($pdf) {
                $this->replacePdf($article, $pdf);
            }

            $article->save();

            return $article->fresh();
        });
    }

    public function delete(PublishedArticle $article): void
    {
        if ($article->is_crossref_sample) {
            abort(422, 'Crossref sample articles are permanent and cannot be deleted.');
        }

        DB::transaction(function () use ($article): void {
            if ($article->pdf_path) {
                Storage::disk('local')->delete($article->pdf_path);
            }

            $article->delete();
        });
    }

    /** @param array<string, mixed> $data */
    private function normalize(array $data, ?PublishedArticle $existing = null): array
    {
        $authors = $data['authors'] ?? $existing?->authors ?? [];

        if (is_string($authors)) {
            $decoded = json_decode($authors, true);
            $authors = is_array($decoded) ? $decoded : [];
        }

        $authors = collect($authors)
            ->filter(fn ($author) => is_array($author) && filled($author['name'] ?? null))
            ->map(fn (array $author) => [
                'name' => trim((string) $author['name']),
                'affiliation' => trim((string) ($author['affiliation'] ?? '')),
                'orcid' => filled($author['orcid'] ?? null) ? trim((string) $author['orcid']) : null,
            ])
            ->values()
            ->all();

        $status = PublishedArticleStatus::tryFrom((string) ($data['status'] ?? $existing?->status?->value ?? 'draft'))
            ?? PublishedArticleStatus::Draft;

        $payload = [
            'title' => trim((string) ($data['title'] ?? $existing?->title ?? '')),
            'authors' => $authors,
            'abstract' => trim((string) ($data['abstract'] ?? $existing?->abstract ?? '')),
            'keywords' => filled($data['keywords'] ?? null) ? trim((string) $data['keywords']) : ($existing?->keywords),
            'article_type' => trim((string) ($data['article_type'] ?? $existing?->article_type ?? 'Research Article')),
            'received_at' => $data['received_at'] ?? $existing?->received_at,
            'revised_at' => array_key_exists('revised_at', $data) ? ($data['revised_at'] ?: null) : $existing?->revised_at,
            'accepted_at' => $data['accepted_at'] ?? $existing?->accepted_at,
            'published_at' => $data['published_at'] ?? $existing?->published_at,
            'journal_issue_id' => array_key_exists('journal_issue_id', $data)
                ? ($data['journal_issue_id'] ?: null)
                : $existing?->journal_issue_id,
            'volume_number' => array_key_exists('volume_number', $data)
                ? ($data['volume_number'] !== null && $data['volume_number'] !== '' ? (int) $data['volume_number'] : null)
                : $existing?->volume_number,
            'issue_number' => array_key_exists('issue_number', $data)
                ? ($data['issue_number'] !== null && $data['issue_number'] !== '' ? (int) $data['issue_number'] : null)
                : $existing?->issue_number,
            'page_start' => array_key_exists('page_start', $data) ? ($data['page_start'] ?: null) : $existing?->page_start,
            'page_end' => array_key_exists('page_end', $data) ? ($data['page_end'] ?: null) : $existing?->page_end,
            'citation' => array_key_exists('citation', $data) ? ($data['citation'] ?: null) : $existing?->citation,
            'copyright' => array_key_exists('copyright', $data) ? ($data['copyright'] ?: null) : $existing?->copyright,
            'license' => array_key_exists('license', $data) ? ($data['license'] ?: null) : $existing?->license,
            'doi' => array_key_exists('doi', $data) ? ($data['doi'] ?: null) : $existing?->doi,
            'status' => $status,
            'sort_order' => (int) ($data['sort_order'] ?? $existing?->sort_order ?? 0),
        ];

        if (array_key_exists('is_crossref_sample', $data)) {
            $payload['is_crossref_sample'] = (bool) $data['is_crossref_sample'];
        } elseif ($existing) {
            $payload['is_crossref_sample'] = $existing->is_crossref_sample;
        }

        if ($status === PublishedArticleStatus::Published && blank($payload['published_at'])) {
            $payload['published_at'] = now()->toDateString();
        }

        return $payload;
    }

    private function storePdf(PublishedArticle $article, UploadedFile $pdf): void
    {
        $filename = Str::uuid().'.pdf';
        $path = $pdf->storeAs('published-articles/'.($article->uuid ?: Str::uuid()), $filename, 'local');

        $article->pdf_path = $path;
        $article->pdf_original_filename = $pdf->getClientOriginalName();
    }

    private function replacePdf(PublishedArticle $article, UploadedFile $pdf): void
    {
        $old = $article->pdf_path;
        $this->storePdf($article, $pdf);

        if ($old && $old !== $article->pdf_path) {
            Storage::disk('local')->delete($old);
        }
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'article';
        $slug = $base;
        $i = 2;

        while (
            PublishedArticle::query()
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    public function buildCitation(PublishedArticle $article): string
    {
        $journal = SiteBranding::journalFullName()
            ?: (string) config('sicama.journal.full_name', 'Journal of Communication Research and Media Studies');
        $authors = $article->authorNames() ?: 'Author';
        $year = optional($article->published_at)?->format('Y') ?: date('Y');
        $volumeIssue = $article->volumeIssueLabel();
        $pages = $article->pagesLabel();

        $parts = ["{$authors} ({$year}). {$article->title}. {$journal}"];

        if ($volumeIssue) {
            $parts[] = $volumeIssue;
        }

        if ($pages) {
            $parts[] = "pp. {$pages}";
        }

        $citation = implode(', ', $parts).'.';

        if ($article->doi) {
            $citation .= ' https://doi.org/'.$article->doi;
        }

        return $citation;
    }
}
