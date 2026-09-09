<?php

namespace App\Models;

use App\Enums\MemberStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Member extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'user_id', 'membership_tier_id', 'membership_number', 'status',
        'joined_at', 'expires_at', 'approved_at', 'approved_by', 'rejection_reason', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => MemberStatus::class,
            'joined_at' => 'datetime',
            'expires_at' => 'datetime',
            'approved_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(MembershipTier::class, 'membership_tier_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(DigitalCredential::class);
    }

    public function isActive(): bool
    {
        return $this->status === MemberStatus::Active
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
