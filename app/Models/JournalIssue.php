<?php

namespace App\Models;

use App\Enums\JournalIssueStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalIssue extends Model
{
    use HasUuid;

    protected $fillable = [
        'journal_volume_id', 'title', 'issue_number', 'description', 'status', 'sort_order', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => JournalIssueStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function volume(): BelongsTo
    {
        return $this->belongsTo(JournalVolume::class, 'journal_volume_id');
    }

    public function callsForPapers(): HasMany
    {
        return $this->hasMany(JournalCallForPapers::class, 'journal_issue_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(JournalSubmission::class, 'journal_issue_id');
    }

    public function label(): string
    {
        if ($this->relationLoaded('volume') && $this->volume) {
            return "{$this->volume->label()} · Issue {$this->issue_number}: {$this->title}";
        }

        return "Issue {$this->issue_number}: {$this->title}";
    }
}
