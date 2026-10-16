<?php

namespace App\Support\Journal;

use App\Models\PublishedArticle;
use App\Support\Settings\SiteBranding;

class PublishedArticlePresenter
{
    /** @return array<string, mixed> */
    public static function toArray(PublishedArticle $article, bool $includeAdmin = false): array
    {
        $journalName = SiteBranding::journalFullName()
            ?: (string) config('sicama.journal.full_name', 'Journal of Communication Research and Media Studies');
        $siteName = SiteBranding::siteName() ?: 'JOCRAMS';

        $payload = [
            'uuid' => $article->uuid,
            'slug' => $article->slug,
            'title' => $article->title,
            'publisher' => $siteName,
            'journal_title' => $journalName,
            'authors' => $article->authors ?? [],
            'author_names' => $article->authorNames(),
            'abstract' => $article->abstract,
            'keywords' => $article->keywords,
            'article_type' => $article->article_type,
            'received_at' => optional($article->received_at)?->toDateString(),
            'revised_at' => optional($article->revised_at)?->toDateString(),
            'accepted_at' => optional($article->accepted_at)?->toDateString(),
            'published_at' => optional($article->published_at)?->toDateString(),
            'volume_number' => $article->volume_number,
            'issue_number' => $article->issue_number,
            'volume_issue_label' => $article->volumeIssueLabel(),
            'page_start' => $article->page_start,
            'page_end' => $article->page_end,
            'pages' => $article->pagesLabel(),
            'citation' => $article->citation,
            'copyright' => $article->copyright,
            'license' => $article->license,
            'doi' => $article->doi,
            'has_pdf' => $article->hasPdf(),
            'pdf_url' => $article->hasPdf() ? $article->pdfPath() : null,
            'landing_url' => $article->landingPath(),
            'is_crossref_sample' => $article->is_crossref_sample,
            'status' => $article->status->value,
            'journal_issue_id' => $article->journal_issue_id,
            'sort_order' => $article->sort_order,
        ];

        if ($includeAdmin) {
            $payload['pdf_original_filename'] = $article->pdf_original_filename;
            $payload['created_at'] = optional($article->created_at)?->toIso8601String();
            $payload['updated_at'] = optional($article->updated_at)?->toIso8601String();
        }

        return $payload;
    }
}
