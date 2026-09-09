<?php

namespace App\Services\Admin;

use App\Enums\MemberStatus;
use App\Models\Member;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class MemberQueryService
{
    /** @param array<string, mixed> $filters */
    public function filter(array $filters): Builder
    {
        $query = Member::query()
            ->with(['user', 'tier'])
            ->latest();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['membership_tier_id'])) {
            $query->where('membership_tier_id', $filters['membership_tier_id']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search): void {
                $q->where('membership_number', 'like', "%{$search}%")
                    ->orWhereHas('user', function (Builder $uq) use ($search): void {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['expires_before'])) {
            $query->where('expires_at', '<=', $filters['expires_before']);
        }

        if (! empty($filters['expires_after'])) {
            $query->where('expires_at', '>=', $filters['expires_after']);
        }

        if (! empty($filters['expiring_within_days'])) {
            $query->where('status', MemberStatus::Active)
                ->whereBetween('expires_at', [now(), now()->addDays((int) $filters['expiring_within_days'])]);
        }

        if (! empty($filters['joined_from'])) {
            $query->where('joined_at', '>=', $filters['joined_from']);
        }

        if (! empty($filters['joined_to'])) {
            $query->where('joined_at', '<=', $filters['joined_to']);
        }

        return $query;
    }

    /** @param array<string, mixed> $filters */
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->filter($filters)->paginate($perPage);
    }
}
