<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CredentialVerification extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'digital_credential_id', 'result', 'ip_address', 'user_agent', 'verified_at',
    ];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(DigitalCredential::class, 'digital_credential_id');
    }
}
