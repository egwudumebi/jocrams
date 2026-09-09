<?php

namespace App\Models;

use App\Enums\Visibility;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Download extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'media_file_id', 'category_id', 'title', 'slug', 'description',
        'visibility', 'allowed_tier_ids', 'download_count', 'is_active', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'visibility' => Visibility::class,
            'allowed_tier_ids' => 'array',
            'is_active' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function mediaFile(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(DownloadLog::class);
    }
}
