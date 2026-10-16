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

    /**
     * Application approved but first SICAMA/SEC dues not yet confirmed.
     * These members can sign in and pay, but must not receive credentials
     * or appear in the public directory until payment succeeds.
     */
    public function isAwaitingMembershipPayment(): bool
    {
        return (bool) data_get($this->metadata, 'awaiting_membership_payment', false);
    }

    /** Paid or admin/imported association member with full membership privileges. */
    public function hasAssociationMembershipAccess(): bool
    {
        return $this->isActive() && ! $this->isAwaitingMembershipPayment();
    }

    public function isSicamaMember(): bool
    {
        $organization = strtolower((string) data_get($this->metadata, 'organization', ''));

        return $organization === 'sicama'
            || str_starts_with(strtoupper((string) $this->membership_number), 'SICAMA');
    }

    public function isSecMember(): bool
    {
        $organization = strtolower((string) data_get($this->metadata, 'organization', ''));

        return $organization === 'sec'
            || str_starts_with(strtoupper((string) $this->membership_number), 'SEC ');
    }

    public function cardRoleTitle(): string
    {
        $role = trim((string) data_get($this->metadata, 'card_role', ''));

        return $role !== '' ? $role : 'Member';
    }

    public function membershipCardTemplateKey(): string
    {
        if ($this->isSicamaMember()) {
            return 'sicama_membership_card';
        }

        if ($this->isSecMember()) {
            return 'sec_membership_card';
        }

        return 'membership_card';
    }
}
