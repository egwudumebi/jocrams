<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'profile_image_path',
        'position',
        'professional_bio',
        'orcid',
        'country',
        'state',
        'city',
        'street',
        'social_links',
        'achievements',
    ];

    protected function casts(): array
    {
        return [
            'social_links' => 'array',
            'achievements' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'uuid');
    }
}
