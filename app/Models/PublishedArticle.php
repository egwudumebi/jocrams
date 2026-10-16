<?php

namespace App\Models;

use App\Enums\PublishedArticleStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PublishedArticle extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'slug',
        'title',
        'authors',
        'abstract',
        'keywords',
        'article_type',
        'received_at',
        'revised_at',
        'accepted_at',
        'published_at',
        'journal_issue_id',
        'volume_number',
        'issue_number',
        'page_start',
        'page_end',
        'pdf_path',
        'pdf_original_filename',
        'citation',
        'copyright',
        'license',
        'doi',
        'status',
        'is_crossref_sample',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'authors' => 'array',
            'received_at' => 'date',
            'revised_at' => 'date',
            'accepted_at' => 'date',
            'published_at' => 'date',
            'status' => PublishedArticleStatus::class,
            'is_crossref_sample' => 'boolean',
            'volume_number' => 'integer',
            'issue_number' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(JournalIssue::class, 'journal_issue_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PublishedArticleStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now()->toDateString());
    }

    public function authorNames(): string
    {
        return collect($this->authors ?? [])
            ->pluck('name')
            ->filter()
            ->implode(', ');
    }

    public function pagesLabel(): ?string
    {
        if ($this->page_start && $this->volume_number) {
            return $this->page_end
                ? "{$this->page_start}–{$this->page_end}"
                : (string) $this->page_start;
        }

        if ($this->page_start) {
            return $this->page_end
                ? "{$this->page_start}–{$this->page_end}"
                : (string) $this->page_start;
        }

        return null;
    }

    public function volumeIssueLabel(): ?string
    {
        if (! $this->volume_number) {
            return null;
        }

        $label = "Vol. {$this->volume_number}";

        if ($this->issue_number) {
            $label .= ", No. {$this->issue_number}";
        }

        return $label;
    }

    public function hasPdf(): bool
    {
        return filled($this->pdf_path) && Storage::disk('local')->exists($this->pdf_path);
    }

    public function landingPath(): string
    {
        return '/articles/'.$this->slug;
    }

    public function pdfPath(): string
    {
        return '/articles/'.$this->slug.'/pdf';
    }
}
