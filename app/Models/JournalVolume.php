<?php

namespace App\Models;

use App\Enums\JournalVolumeStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalVolume extends Model
{
    use HasUuid;

    protected $fillable = [
        'title', 'volume_number', 'year', 'description', 'status', 'sort_order', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => JournalVolumeStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function issues(): HasMany
    {
        return $this->hasMany(JournalIssue::class, 'journal_volume_id')->orderBy('sort_order')->orderBy('issue_number');
    }

    public function label(): string
    {
        $parts = ["Vol. {$this->volume_number}"];

        if ($this->year) {
            $parts[] = (string) $this->year;
        }

        if ($this->title && ! str_contains(strtolower($this->title), 'volume')) {
            $parts[] = $this->title;
        }

        return implode(' · ', $parts);
    }
}
