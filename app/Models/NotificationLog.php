<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\NotificationLogStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class NotificationLog extends Model
{
    protected $fillable = [
        'notification_template_id', 'notifiable_type', 'notifiable_id',
        'channel', 'event', 'recipient', 'subject', 'body', 'status', 'sent_at', 'read_at', 'error',
    ];

    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'status' => NotificationLogStatus::class,
            'sent_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'notification_template_id');
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }
}
