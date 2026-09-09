<?php

namespace App\Services\Admin;

use App\Enums\ApplicationStatus;
use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\MembershipApplication;
use App\Models\User;
use App\Services\Membership\MembershipApplicationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BatchOperationService
{
    public function __construct(
        private readonly MembershipApplicationService $applicationService,
        private readonly ActivityLogService $activityLog,
    ) {}

    /** @param list<string> $applicationUuids */
    public function batchApproveApplications(array $applicationUuids, User $admin, ?string $notes = null): array
    {
        $results = ['approved' => [], 'failed' => []];

        foreach ($applicationUuids as $uuid) {
            try {
                $application = MembershipApplication::query()->where('uuid', $uuid)->firstOrFail();
                $member = $this->applicationService->approve($application, $admin, $notes);
                $results['approved'][] = $member->uuid;
            } catch (\Throwable $e) {
                $results['failed'][] = ['uuid' => $uuid, 'error' => $e->getMessage()];
            }
        }

        $this->activityLog->log($admin, 'batch.applications.approve', properties: $results);

        return $results;
    }

    /** @param list<string> $applicationUuids */
    public function batchRejectApplications(array $applicationUuids, User $admin, string $reason): array
    {
        $results = ['rejected' => [], 'failed' => []];

        foreach ($applicationUuids as $uuid) {
            try {
                $application = MembershipApplication::query()->where('uuid', $uuid)->firstOrFail();
                $this->applicationService->reject($application, $admin, $reason);
                $results['rejected'][] = $uuid;
            } catch (\Throwable $e) {
                $results['failed'][] = ['uuid' => $uuid, 'error' => $e->getMessage()];
            }
        }

        $this->activityLog->log($admin, 'batch.applications.reject', properties: $results);

        return $results;
    }

    /** @param list<string> $memberUuids */
    public function batchUpdateMemberStatus(array $memberUuids, User $admin, MemberStatus $status): array
    {
        return DB::transaction(function () use ($memberUuids, $admin, $status) {
            $updated = [];

            foreach ($memberUuids as $uuid) {
                $member = Member::query()->where('uuid', $uuid)->firstOrFail();
                $member->update(['status' => $status]);
                $updated[] = $uuid;
            }

            $this->activityLog->log($admin, 'batch.members.status_update', properties: [
                'status' => $status->value,
                'member_uuids' => $updated,
            ]);

            return ['updated' => $updated, 'status' => $status->value];
        });
    }

    /** @param list<string> $applicationUuids */
    public function validateBatchApproval(array $applicationUuids): void
    {
        if ($applicationUuids === []) {
            throw ValidationException::withMessages(['application_uuids' => ['At least one application is required.']]);
        }

        $count = MembershipApplication::query()
            ->whereIn('uuid', $applicationUuids)
            ->whereIn('status', [ApplicationStatus::Submitted, ApplicationStatus::UnderReview])
            ->count();

        if ($count !== count($applicationUuids)) {
            throw ValidationException::withMessages([
                'application_uuids' => ['One or more applications are not eligible for approval.'],
            ]);
        }
    }
}
