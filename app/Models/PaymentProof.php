<?php

namespace App\Models;

use App\Enums\PaymentProofStatus;
use App\Enums\PaymentPurpose;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PaymentProof extends Model
{
    use HasUuid;

    protected $fillable = [
        'user_id',
        'member_id',
        'purpose',
        'amount',
        'currency',
        'payer_reference',
        'member_note',
        'status',
        'rejection_reason',
        'media_file_id',
        'payment_id',
        'related_type',
        'related_id',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'purpose' => PaymentPurpose::class,
            'status' => PaymentProofStatus::class,
            'amount' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function mediaFile(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }
}
