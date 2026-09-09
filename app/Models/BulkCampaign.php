<?php

namespace App\Models;

use App\Enums\BulkCampaignStatus;
use App\Enums\NotificationChannel;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BulkCampaign extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'created_by', 'name', 'channel', 'subject', 'body', 'audience_filter',
        'status', 'scheduled_at', 'started_at', 'completed_at',
        'total_recipients', 'sent_count', 'failed_count',
    ];

    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'status' => BulkCampaignStatus::class,
            'audience_filter' => 'array',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(BulkCampaignRecipient::class);
    }
}
