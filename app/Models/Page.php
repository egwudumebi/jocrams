<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Page extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'title', 'slug', 'body', 'meta', 'status', 'visibility', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')->where('visibility', 'public');
    }
}
