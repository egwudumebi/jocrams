<?php

namespace App\Models;

use App\Enums\JournalSubmissionStatus;
use App\Enums\JournalVisibility;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalSubmission extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid', 'author_id', 'call_for_papers_id', 'journal_issue_id', 'title', 'slug', 'author_name', 'author_email', 'abstract',
        'category', 'keywords', 'mins_read', 'references', 'document_path', 'production_document_path',
        'status', 'submission_fee_amount', 'publication_fee_amount',
        'submission_payment_id', 'publication_payment_id',
        'visibility', 'reviewer_id', 'review_comment', 'rejection_reason', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => JournalSubmissionStatus::class,
            'visibility' => JournalVisibility::class,
            'references' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id', 'uuid');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id', 'uuid');
    }

    public function callForPapers(): BelongsTo
    {
        return $this->belongsTo(JournalCallForPapers::class, 'call_for_papers_id');
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(JournalIssue::class, 'journal_issue_id');
    }

    public function submissionPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'submission_payment_id');
    }

    public function publicationPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'publication_payment_id');
    }
}
