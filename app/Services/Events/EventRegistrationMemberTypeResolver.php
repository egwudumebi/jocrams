<?php

namespace App\Services\Events;

use App\Enums\EventMemberType;
use App\Models\User;

class EventRegistrationMemberTypeResolver
{
    public function resolve(?User $user, ?string $requested = null): string
    {
        if ($requested && in_array($requested, EventMemberType::values(), true)) {
            return $requested;
        }

        if ($user?->member?->isActive()) {
            return EventMemberType::Member->value;
        }

        return EventMemberType::NonMember->value;
    }
}
