<?php

namespace App\Models;

use App\Enums\SupportMessageSource;
use App\Enums\SupportMessageStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactInquiry extends Model
{
    use HasUuid;

    protected $fillable = [
        'user_id', 'name', 'email', 'phone', 'subject', 'message', 'category',
        'source', 'branch_id', 'status', 'assigned_to', 'last_response',
        'responded_at', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SupportMessageStatus::class,
            'source' => SupportMessageSource::class,
            'responded_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
