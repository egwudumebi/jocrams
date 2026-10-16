<?php

namespace App\Services\Membership;

use App\Enums\MemberStatus;
use App\Models\Member;
use App\Support\Membership\PublicMemberPresenter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class PublicMemberQueryService
{
    public function activeQuery(): Builder
    {
        return Member::query()
            ->with(['user.profile', 'tier'])
            ->where('status', MemberStatus::Active)
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            // Exclude applicants still awaiting first dues payment.
            ->where(function (Builder $query): void {
                $query->whereNull('metadata->awaiting_membership_payment')
                    ->orWhere('metadata->awaiting_membership_payment', false)
                    ->orWhere('metadata->awaiting_membership_payment', 0)
                    ->orWhere('metadata->awaiting_membership_payment', 'false')
                    ->orWhere('metadata->awaiting_membership_payment', '0');
            })
            ->latest('joined_at');
    }

    /**
     * @param  array{search?: string, page?: int, per_page?: int}  $filters
     * @return array<string, mixed>
     */
    public function paginate(array $filters = [], int $perPage = 24): array
    {
        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? $perPage)));
        $query = $this->activeQuery();

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $q) use ($search): void {
                $q->where('membership_number', 'like', "%{$search}%")
                    ->orWhereHas('user', function (Builder $uq) use ($search): void {
                        $uq->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('tier', function (Builder $tq) use ($search): void {
                        $tq->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('user.profile', function (Builder $pq) use ($search): void {
                        $pq->where('position', 'like', "%{$search}%")
                            ->orWhere('city', 'like', "%{$search}%")
                            ->orWhere('state', 'like', "%{$search}%");
                    });
            });
        }

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->paginate($perPage);

        return [
            'data' => $paginator->getCollection()
                ->map(fn (Member $member) => PublicMemberPresenter::listItem($member))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }

    public function findActive(string $uuid): ?Member
    {
        return $this->activeQuery()->where('uuid', $uuid)->first();
    }
}
