<?php

namespace App\Models;

use App\Enums\Visibility;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'organizer_id', 'category_id', 'title', 'slug', 'description', 'body',
        'location', 'virtual_url', 'starts_at', 'ends_at',
        'registration_opens_at', 'registration_closes_at', 'max_attendees',
        'fee', 'currency', 'visibility', 'status', 'featured_image_media_id',
    ];

    protected function casts(): array
    {
        return [
            'visibility' => Visibility::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'registration_opens_at' => 'datetime',
            'registration_closes_at' => 'datetime',
            'fee' => 'decimal:2',
        ];
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(EventSession::class)->orderBy('sort_order');
    }

    public function pricingTiers(): HasMany
    {
        return $this->hasMany(EventPricingTier::class);
    }

    public function featuredImage(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'featured_image_media_id');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->where('visibility', Visibility::Public);
    }

    public function scopeUpcoming($query)
    {
        return $query->where(function ($query): void {
            $query->where(function ($inner): void {
                $inner->whereNotNull('ends_at')
                    ->where('ends_at', '>=', now());
            })->orWhere(function ($inner): void {
                $inner->whereNull('ends_at')
                    ->where('starts_at', '>=', now());
            });
        });
    }

    public function isUpcoming(): bool
    {
        if ($this->ends_at) {
            return $this->ends_at->gte(now());
        }

        return $this->starts_at?->gte(now()) ?? false;
    }
}
