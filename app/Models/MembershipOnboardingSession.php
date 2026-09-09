<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipOnboardingSession extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'email',
        'phone',
        'first_name',
        'last_name',
        'email_verified',
        'state',
        'professional_payload',
        'expires_at',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'email_verified' => 'boolean',
            'professional_payload' => 'array',
            'expires_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function otps(): HasMany
    {
        return $this->hasMany(MembershipOnboardingOtp::class, 'session_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && now()->greaterThan($this->expires_at);
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}") ?: 'Onboarding Applicant';
    }
}
