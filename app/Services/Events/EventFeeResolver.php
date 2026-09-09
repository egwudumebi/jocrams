<?php

namespace App\Services\Events;

use App\Enums\EventMemberType;
use App\Models\Event;

class EventFeeResolver
{
    public function resolveFee(Event $event, string $memberType): float
    {
        $tier = $event->pricingTiers()->where('category', $memberType)->first();

        if ($tier) {
            return (float) $tier->fee;
        }

        return (float) $event->fee;
    }

    /** @return array<string, float> */
    public function feesByCategory(Event $event): array
    {
        $fees = [];

        foreach (EventMemberType::values() as $category) {
            $fees[$category] = $this->resolveFee($event, $category);
        }

        return $fees;
    }
}
