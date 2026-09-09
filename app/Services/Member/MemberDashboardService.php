<?php

namespace App\Services\Member;

use App\Models\DigitalCredential;
use App\Models\EventRegistration;
use App\Models\JournalSubmission;
use App\Models\User;

class MemberDashboardService
{
    /** @return array<string, mixed> */
    public function snapshot(User $user): array
    {
        $member = $user->member()->with('tier')->first();

        $registrations = EventRegistration::query()
            ->with('event')
            ->where('user_id', $user->id)
            ->whereHas('event')
            ->get();

        $upcomingEvents = $registrations
            ->filter(fn (EventRegistration $registration) => $registration->event?->starts_at?->isFuture())
            ->sortBy(fn (EventRegistration $registration) => $registration->event?->starts_at)
            ->take(5)
            ->map(fn (EventRegistration $registration) => [
                'uuid' => $registration->event?->uuid,
                'title' => $registration->event?->title,
                'starts_at' => $registration->event?->starts_at?->toIso8601String(),
                'location' => $registration->event?->location,
                'status' => $registration->event?->status,
                'registration_status' => $registration->status,
            ])
            ->values()
            ->all();

        $eventsAttended = $registrations
            ->filter(fn (EventRegistration $registration) => $registration->event?->starts_at?->isPast())
            ->count();

        $journalSubmissions = JournalSubmission::query()
            ->where('author_id', $user->uuid)
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->map(fn (JournalSubmission $submission) => [
                'uuid' => $submission->uuid,
                'title' => $submission->title,
                'status' => $submission->status->value,
                'updated_at' => $submission->updated_at?->toIso8601String(),
            ])
            ->all();

        return [
            'membership' => $member ? [
                'uuid' => $member->uuid,
                'membership_number' => $member->membership_number,
                'status' => $member->status->value,
                'tier' => $member->tier?->name,
                'joined_at' => $member->joined_at?->toIso8601String(),
                'expires_at' => $member->expires_at?->toIso8601String(),
            ] : null,
            'events_attended' => $eventsAttended,
            'upcoming_events' => $upcomingEvents,
            'journal_submissions_count' => JournalSubmission::query()->where('author_id', $user->uuid)->count(),
            'journal_submissions' => $journalSubmissions,
            'credentials_count' => $member
                ? DigitalCredential::query()->where('member_id', $member->id)->count()
                : 0,
        ];
    }
}
