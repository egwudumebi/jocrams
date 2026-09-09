<?php

namespace App\Services\Admin;

use App\Enums\ApplicationStatus;
use App\Models\Approval;
use App\Models\Download;
use App\Models\Event;
use App\Models\Member;
use App\Models\MembershipApplication;
use App\Models\NewsArticle;
use App\Models\Payment;
use Illuminate\Support\Carbon;

class DashboardService
{
    /** @return array<string, mixed> */
    public function overview(): array
    {
        return [
            'stats' => $this->legacyStats(),
            'metrics' => $this->metrics(),
            'upcoming_events' => $this->upcomingEvents(),
            'registration_analytics' => $this->registrationAnalytics(),
            'pending_applications' => $this->pendingApplications(),
            'recent_publications' => $this->recentPublications(),
        ];
    }

    /** @return array<string, mixed> */
    private function legacyStats(): array
    {
        return [
            'members' => [
                'total' => Member::query()->count(),
                'active' => Member::query()->where('status', 'active')->count(),
                'expiring_soon' => Member::query()
                    ->where('status', 'active')
                    ->whereBetween('expires_at', [now(), now()->addDays(30)])
                    ->count(),
            ],
            'applications' => [
                'pending' => MembershipApplication::query()
                    ->whereIn('status', [ApplicationStatus::Submitted, ApplicationStatus::UnderReview])
                    ->count(),
            ],
            'approvals' => [
                'pending' => Approval::query()->where('status', 'pending')->count(),
            ],
            'payments' => [
                'today' => Payment::query()
                    ->where('status', 'successful')
                    ->whereDate('paid_at', today())
                    ->sum('amount'),
                'pending' => Payment::query()->where('status', 'pending')->count(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function metrics(): array
    {
        $activeMembers = Member::query()->where('status', 'active')->count();
        $newApplicationsThisMonth = MembershipApplication::query()
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
        $newApplicationsLastMonth = MembershipApplication::query()
            ->whereBetween('created_at', [
                now()->subMonth()->startOfMonth(),
                now()->subMonth()->endOfMonth(),
            ])
            ->count();

        $membersThisMonth = Member::query()
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
        $membersLastMonth = Member::query()
            ->whereBetween('created_at', [
                now()->subMonth()->startOfMonth(),
                now()->subMonth()->endOfMonth(),
            ])
            ->count();

        return [
            'active_members' => [
                'value' => $activeMembers,
                'change_percent' => $this->percentChange($membersLastMonth, $membersThisMonth),
            ],
            'new_applications_month' => [
                'value' => $newApplicationsThisMonth,
                'change_percent' => $this->percentChange($newApplicationsLastMonth, $newApplicationsThisMonth),
            ],
            'upcoming_events' => Event::query()
                ->where('starts_at', '>=', now())
                ->whereIn('status', ['published', 'draft'])
                ->count(),
            'published_documents' => Download::query()
                ->where('is_active', true)
                ->whereNotNull('published_at')
                ->count()
                + NewsArticle::query()->where('status', 'published')->count(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function upcomingEvents(): array
    {
        return Event::query()
            ->withCount('registrations')
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->limit(4)
            ->get()
            ->map(fn (Event $event) => [
                'uuid' => $event->uuid,
                'title' => $event->title,
                'starts_at' => $event->starts_at?->toIso8601String(),
                'status' => $event->status,
                'registrants_count' => $event->registrations_count,
                'location' => $event->location,
            ])
            ->all();
    }

    /** @return array<string, mixed> */
    private function registrationAnalytics(): array
    {
        $months = collect(range(0, 11))
            ->map(fn (int $offset) => now()->startOfMonth()->subMonths(11 - $offset));

        $registrations = $months->map(fn (Carbon $month) => MembershipApplication::query()
            ->whereYear('created_at', $month->year)
            ->whereMonth('created_at', $month->month)
            ->count())
            ->all();

        $renewals = $months->map(fn (Carbon $month) => Member::query()
            ->where('status', 'active')
            ->whereYear('updated_at', $month->year)
            ->whereMonth('updated_at', $month->month)
            ->count())
            ->all();

        $maxValue = max(1, ...$registrations, ...$renewals);

        return [
            'labels' => $months->map(fn (Carbon $month) => $month->format('M'))->all(),
            'registrations' => $registrations,
            'renewals' => $renewals,
            'target' => (int) ceil($maxValue * 0.85),
            'max_value' => $maxValue,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function pendingApplications(): array
    {
        return MembershipApplication::query()
            ->with('tier')
            ->whereIn('status', [
                ApplicationStatus::Submitted,
                ApplicationStatus::UnderReview,
                ApplicationStatus::Approved,
                ApplicationStatus::Draft,
            ])
            ->latest('updated_at')
            ->limit(6)
            ->get()
            ->map(fn (MembershipApplication $application) => [
                'uuid' => $application->uuid,
                'name' => $application->applicant_name,
                'company' => data_get($application->form_data, 'organization'),
                'submitted_at' => ($application->submitted_at ?? $application->created_at)?->toIso8601String(),
                'status' => $application->status->value,
                'status_label' => $this->applicationStatusLabel($application->status),
                'tier' => $application->tier?->name,
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function recentPublications(): array
    {
        $downloads = Download::query()
            ->with('mediaFile')
            ->where('is_active', true)
            ->latest('updated_at')
            ->limit(4)
            ->get()
            ->map(fn (Download $download) => [
                'uuid' => $download->uuid,
                'title' => $download->title,
                'type' => $this->fileTypeLabel($download->mediaFile?->mime_type),
                'published_at' => ($download->published_at ?? $download->updated_at)?->toIso8601String(),
                'kind' => 'download',
            ]);

        if ($downloads->isNotEmpty()) {
            return $downloads->all();
        }

        return NewsArticle::query()
            ->where('status', 'published')
            ->latest('published_at')
            ->limit(4)
            ->get()
            ->map(fn (NewsArticle $article) => [
                'uuid' => $article->uuid,
                'title' => $article->title,
                'type' => 'Article',
                'published_at' => $article->published_at?->toIso8601String(),
                'kind' => 'news',
            ])
            ->all();
    }

    private function percentChange(int $previous, int $current): float
    {
        if ($previous === 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function applicationStatusLabel(ApplicationStatus $status): string
    {
        return match ($status) {
            ApplicationStatus::Submitted, ApplicationStatus::UnderReview => 'Pending Review',
            ApplicationStatus::Approved => 'Approved',
            ApplicationStatus::Draft => 'Awaiting Payment',
            ApplicationStatus::Rejected => 'Rejected',
            ApplicationStatus::Cancelled => 'Cancelled',
        };
    }

    private function fileTypeLabel(?string $mimeType): string
    {
        return match (true) {
            str_contains((string) $mimeType, 'pdf') => 'PDF',
            str_contains((string) $mimeType, 'word') || str_contains((string) $mimeType, 'document') => 'Word',
            default => 'File',
        };
    }
}
