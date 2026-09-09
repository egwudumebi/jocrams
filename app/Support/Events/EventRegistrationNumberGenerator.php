<?php

namespace App\Support\Events;

use App\Models\Event;
use Illuminate\Support\Str;

class EventRegistrationNumberGenerator
{
    public static function generate(): string
    {
        do {
            $number = 'EVT-'.strtoupper(Str::random(8));
        } while (\App\Models\EventRegistration::query()->where('registration_number', $number)->exists());

        return $number;
    }
}
