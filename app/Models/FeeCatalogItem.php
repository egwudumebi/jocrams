<?php

namespace App\Models;

use App\Enums\FeeCatalogCategory;
use App\Enums\FeeCatalogType;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeeCatalogItem extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'code',
        'category',
        'fee_type',
        'amount',
        'currency',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'category' => FeeCatalogCategory::class,
            'fee_type' => FeeCatalogType::class,
            'amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
