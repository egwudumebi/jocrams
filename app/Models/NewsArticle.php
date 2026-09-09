<?php

namespace App\Models;

use App\Enums\Visibility;
use App\Models\Concerns\HasUuid;
use App\Support\Html\HtmlSanitizer;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NewsArticle extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'author_id', 'category_id', 'title', 'slug', 'excerpt', 'body',
        'featured_image_media_id', 'status', 'visibility', 'published_at', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'visibility' => Visibility::class,
            'published_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->where('visibility', Visibility::Public)
            ->where('published_at', '<=', now());
    }

    protected function body(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => HtmlSanitizer::normalize($value) ?? $value,
        );
    }
}
