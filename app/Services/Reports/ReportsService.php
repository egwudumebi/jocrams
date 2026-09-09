<?php

namespace App\Services\Reports;

use App\Enums\ApplicationStatus;
use App\Enums\JournalSubmissionStatus;
use App\Enums\MemberStatus;
use App\Enums\PaymentStatus;
use App\Enums\SupportMessageStatus;
use App\Models\ActivityLog;
use App\Models\ContactInquiry;
use App\Models\Event;
use App\Models\JournalSubmission;
use App\Models\Member;
use App\Models\MembershipApplication;
use App\Models\Payment;
use Illuminate\Support\Carbon;

class ReportsService
{
    /** @return array<string, mixed> */
    public function overview(): array
    {
        return [
            'members_total' => Member::query()->count(),
            'members_active' => Member::query()->where('status', MemberStatus::Active)->count(),
            'applications_pending' => MembershipApplication::query()
                ->whereIn('status', [ApplicationStatus::Submitted, ApplicationStatus::UnderReview])
                ->count(),
            'applications_growth_trend_6_months' => $this->applicationGrowthTrend(),
            'journal_submissions_pending' => JournalSubmission::query()
                ->whereIn('status', [
                    JournalSubmissionStatus::Submitted,
                    JournalSubmissionStatus::UnderReview,
                    JournalSubmissionStatus::RevisionRequested,
                    JournalSubmissionStatus::Resubmitted,
                ])
                ->count(),
            'payments_total' => (float) Payment::query()
                ->where('status', PaymentStatus::Successful)
                ->sum('amount'),
            'events_total' => Event::query()->count(),
            'support_open' => ContactInquiry::query()
                ->whereIn('status', [SupportMessageStatus::New, SupportMessageStatus::Responded])
                ->count(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function recentActivities(int $limit = 50): array
    {
        $limit = max(1, min($limit, 200));

        return ActivityLog::query()
            ->with('user')
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (ActivityLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'actor_id' => $log->user_id,
                'actor_name' => $log->user?->name,
                'actor_email' => $log->user?->email,
                'subject_type' => $log->subject_type,
                'subject_id' => $log->subject_id,
                'properties' => $log->properties,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'occurred_at' => $log->created_at?->toIso8601String(),
                'created_at' => $log->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /** @return list<array{month: string, applications: int}> */
    private function applicationGrowthTrend(): array
    {
        $months = collect(range(0, 5))
            ->map(fn (int $offset) => now()->startOfMonth()->subMonths(5 - $offset));

        return $months->map(function (Carbon $month): array {
            return [
                'month' => $month->format('Y-m'),
                'applications' => MembershipApplication::query()
                    ->whereYear('created_at', $month->year)
                    ->whereMonth('created_at', $month->month)
                    ->count(),
            ];
        })->all();
    }
}
