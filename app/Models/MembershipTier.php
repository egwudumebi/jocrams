<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MembershipTier extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'description', 'annual_dues', 'currency',
        'benefits', 'sort_order', 'is_active', 'min_documents_required', 'requires_approval',
    ];

    protected function casts(): array
    {
        return [
            'annual_dues' => 'decimal:2',
            'benefits' => 'array',
            'is_active' => 'boolean',
            'requires_approval' => 'boolean',
        ];
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(MembershipApplication::class);
    }
}
