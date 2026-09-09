<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EditorialBoardMember extends Model
{
    use HasUuid;

    protected $fillable = [
        'user_id', 'name', 'role_title', 'affiliation', 'bio', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'uuid');
    }

    public function callsForPapers(): BelongsToMany
    {
        return $this->belongsToMany(
            JournalCallForPapers::class,
            'journal_call_editorial_board',
            'editorial_board_member_id',
            'journal_call_for_papers_id',
        );
    }
}
