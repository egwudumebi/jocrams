<?php

namespace App\Services\Events;

use App\Enums\EventMemberType;
use App\Enums\Visibility;
use App\Models\Event;
use App\Models\User;
use App\Services\Membership\MediaUploadService;
use App\Support\Events\EventSlugGenerator;
use App\Support\Html\HtmlSanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class EventService
{
    public function __construct(private readonly MediaUploadService $mediaUploadService) {}

    /** @param array<string, mixed> $data */
    public function create(User $organizer, array $data, ?UploadedFile $banner = null): Event
    {
        return DB::transaction(function () use ($organizer, $data, $banner): Event {
            $sessions = $data['sessions'] ?? [];
            $pricingTiers = $data['pricing_tiers'] ?? [];
            unset($data['sessions'], $data['pricing_tiers'], $data['banner']);

            if (isset($data['description'])) {
                $data['description'] = HtmlSanitizer::clean($data['description']);
            }

            if (isset($data['body'])) {
                $data['body'] = HtmlSanitizer::clean($data['body']);
            }

            $event = Event::query()->create([
                ...$data,
                'slug' => EventSlugGenerator::uniqueForTitle((string) $data['title']),
                'organizer_id' => $organizer->id,
                'status' => $data['status'] ?? 'draft',
                'visibility' => $data['visibility'] ?? Visibility::Public->value,
                'currency' => $data['currency'] ?? 'NGN',
                'fee' => $data['fee'] ?? 0,
            ]);

            if ($banner) {
                $this->attachBanner($event, $banner, $organizer);
            }

            if ($sessions !== []) {
                $this->syncSessions($event, $sessions);
            }

            if ($pricingTiers !== []) {
                $this->syncPricingTiers($event, $pricingTiers);
            }

            return $event->fresh(['category', 'sessions', 'pricingTiers', 'featuredImage']);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Event $event, User $actor, array $data, ?UploadedFile $banner = null): Event
    {
        return DB::transaction(function () use ($event, $data, $banner, $actor): Event {
            $sessions = array_key_exists('sessions', $data) ? $data['sessions'] : null;
            $pricingTiers = array_key_exists('pricing_tiers', $data) ? $data['pricing_tiers'] : null;
            unset($data['sessions'], $data['pricing_tiers'], $data['banner']);

            if (isset($data['title'])) {
                $data['slug'] = EventSlugGenerator::uniqueForTitle((string) $data['title'], $event->id);
            }

            if (array_key_exists('description', $data)) {
                $data['description'] = HtmlSanitizer::clean($data['description']);
            }

            if (array_key_exists('body', $data)) {
                $data['body'] = HtmlSanitizer::clean($data['body']);
            }

            $event->update($data);

            if ($banner) {
                $this->attachBanner($event, $banner, $actor);
            }

            if (is_array($sessions)) {
                $this->syncSessions($event, $sessions);
            }

            if (is_array($pricingTiers)) {
                $this->syncPricingTiers($event, $pricingTiers);
            }

            return $event->fresh(['category', 'sessions', 'pricingTiers', 'featuredImage']);
        });
    }

    public function delete(Event $event): void
    {
        $event->delete();
    }

    /** @param list<array<string, mixed>> $sessions */
    public function syncSessions(Event $event, array $sessions): void
    {
        $event->sessions()->delete();

        $earliestStart = null;
        $latestEnd = null;

        foreach (array_values($sessions) as $index => $session) {
            $startsAt = $session['starts_at'] ?? null;
            $title = trim((string) ($session['title'] ?? ''));

            if (! $startsAt || $title === '') {
                continue;
            }

            $endsAt = $session['ends_at'] ?? null;

            $event->sessions()->create([
                'title' => $title,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'sort_order' => (int) ($session['sort_order'] ?? $index),
            ]);

            if ($earliestStart === null || strtotime((string) $startsAt) < strtotime((string) $earliestStart)) {
                $earliestStart = $startsAt;
            }

            $sessionEnd = $endsAt ?? $startsAt;
            if ($latestEnd === null || strtotime((string) $sessionEnd) > strtotime((string) $latestEnd)) {
                $latestEnd = $sessionEnd;
            }
        }

        if ($earliestStart !== null) {
            $event->update([
                'starts_at' => $earliestStart,
                'ends_at' => $latestEnd,
            ]);
        }
    }

    /** @param list<array<string, mixed>> $pricingTiers */
    public function syncPricingTiers(Event $event, array $pricingTiers): void
    {
        $event->pricingTiers()->delete();

        $seen = [];

        foreach ($pricingTiers as $tier) {
            $category = (string) ($tier['category'] ?? '');

            if (! in_array($category, EventMemberType::values(), true) || isset($seen[$category])) {
                continue;
            }

            $seen[$category] = true;

            $event->pricingTiers()->create([
                'category' => $category,
                'fee' => $tier['fee'] ?? 0,
            ]);
        }
    }

    private function attachBanner(Event $event, UploadedFile $banner, User $actor): void
    {
        if ($event->featuredImage) {
            $this->mediaUploadService->delete($event->featuredImage);
        }

        $media = $this->mediaUploadService->store(
            $banner,
            'event-banners',
            $actor,
            Visibility::Public,
            'public',
        );

        $event->update(['featured_image_media_id' => $media->id]);
    }
}
