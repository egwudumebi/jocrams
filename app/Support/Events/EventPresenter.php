<?php

namespace App\Support\Events;

use App\Models\Event;
use App\Services\Events\EventFeeResolver;

class EventPresenter
{
    /** @return array<string, mixed> */
    public static function forEvent(Event $event): array
    {
        $event->loadMissing(['category', 'organizer', 'sessions', 'pricingTiers', 'featuredImage']);
        $event->loadCount(['registrations as confirmed_registrations_count' => fn ($query) => $query->where('status', 'confirmed')]);

        $payload = $event->toArray();
        $payload['banner_url'] = self::bannerUrl($event);
        $payload['sessions'] = $event->sessions
            ->sortBy('sort_order')
            ->values()
            ->map(fn ($session) => [
                'id' => $session->id,
                'title' => $session->title,
                'starts_at' => $session->starts_at,
                'ends_at' => $session->ends_at,
                'sort_order' => $session->sort_order,
            ])
            ->all();
        $payload['pricing_tiers'] = $event->pricingTiers
            ->map(fn ($tier) => [
                'id' => $tier->id,
                'category' => $tier->category,
                'fee' => $tier->fee,
            ])
            ->values()
            ->all();
        $payload['fees_by_category'] = app(EventFeeResolver::class)->feesByCategory($event);
        $payload['registration_stats'] = self::registrationStats($event);
        $payload['is_upcoming'] = $event->isUpcoming();

        return $payload;
    }

    /** @return array<string, mixed> */
    private static function registrationStats(Event $event): array
    {
        $confirmed = (int) ($event->confirmed_registrations_count ?? $event->registrations()->where('status', 'confirmed')->count());
        $max = $event->max_attendees;
        $open = true;

        if ($event->registration_opens_at && now()->lt($event->registration_opens_at)) {
            $open = false;
        }

        if ($event->registration_closes_at && now()->gt($event->registration_closes_at)) {
            $open = false;
        }

        return [
            'confirmed_count' => $confirmed,
            'spots_remaining' => $max ? max(0, $max - $confirmed) : null,
            'is_full' => $max ? $confirmed >= $max : false,
            'registration_open' => $open && $event->status === 'published',
        ];
    }

    public static function bannerUrl(Event $event): ?string
    {
        if (! $event->featured_image_media_id) {
            return null;
        }

        return url('/api/v1/public/events/'.$event->uuid.'/banner');
    }
}
