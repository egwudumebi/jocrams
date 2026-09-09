<?php

namespace App\Models;

use App\Enums\CredentialType;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DigitalCredential extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'member_id', 'type', 'title', 'template_key', 'verification_token',
        'file_media_id', 'issued_at', 'expires_at', 'revoked_at', 'revocation_reason', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => CredentialType::class,
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'file_media_id');
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(CredentialVerification::class);
    }

    public function isValid(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
