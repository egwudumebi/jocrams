<?php

namespace App\Models;

use App\Enums\Visibility;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MediaFile extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'uploaded_by', 'disk', 'path', 'filename', 'original_filename',
        'mime_type', 'size', 'collection', 'mediable_type', 'mediable_id',
        'visibility', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'visibility' => Visibility::class,
            'metadata' => 'array',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    public function downloads(): HasMany
    {
        return $this->hasMany(Download::class);
    }
}
