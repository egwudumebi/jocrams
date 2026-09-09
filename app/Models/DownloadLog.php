<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DownloadLog extends Model
{
    protected $fillable = ['download_id', 'user_id', 'ip_address', 'user_agent'];

    public function download(): BelongsTo
    {
        return $this->belongsTo(Download::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
