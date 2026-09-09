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
            ->latest('joined_at');
    }

    /** @return array<string, mixed> */
    public function paginate(int $perPage = 20): array
    {
        /** @var LengthAwarePaginator $paginator */
        $paginator = $this->activeQuery()->paginate($perPage);

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
            ],
        ];
    }

    public function findActive(string $uuid): ?Member
    {
        return $this->activeQuery()->where('uuid', $uuid)->first();
    }
}
