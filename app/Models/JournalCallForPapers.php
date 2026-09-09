<?php

namespace App\Models;

use App\Enums\JournalCallStatus;
use App\Models\Concerns\HasUuid;
use App\Support\Html\HtmlSanitizer;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalCallForPapers extends Model
{
    use HasUuid;

    protected $table = 'journal_calls_for_papers';

    protected $fillable = [
        'created_by', 'journal_issue_id', 'title', 'slug', 'excerpt', 'body',
        'opens_at', 'closes_at', 'submission_fee', 'publication_fee',
        'currency', 'status', 'topics',
    ];

    protected function casts(): array
    {
        return [
            'status' => JournalCallStatus::class,
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'submission_fee' => 'decimal:2',
            'publication_fee' => 'decimal:2',
            'topics' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(JournalIssue::class, 'journal_issue_id');
    }

    public function editorialBoardMembers(): BelongsToMany
    {
        return $this->belongsToMany(
            EditorialBoardMember::class,
            'journal_call_editorial_board',
            'journal_call_for_papers_id',
            'editorial_board_member_id',
        )->orderBy('sort_order');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(JournalSubmission::class, 'call_for_papers_id');
    }

    public function isOpen(): bool
    {
        if ($this->status !== JournalCallStatus::Open) {
            return false;
        }

        $now = now();

        if ($this->opens_at && $this->opens_at->isFuture()) {
            return false;
        }

        if ($this->closes_at && $this->closes_at->isPast()) {
            return false;
        }

        return true;
    }

    protected function body(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => HtmlSanitizer::normalize($value) ?? $value,
        );
    }
}
