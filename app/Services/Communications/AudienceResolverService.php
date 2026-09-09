<?php

namespace App\Services\Communications;

use App\Enums\MemberStatus;
use App\Enums\NotificationChannel;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AudienceResolverService
{
    /** @param array<string, mixed> $filter */
    public function resolve(array $filter): Builder
    {
        $query = User::query()->where('status', 'active');

        if (! empty($filter['member_status'])) {
            $query->whereHas('member', fn (Builder $q) => $q->where('status', $filter['member_status']));
        }

        if (! empty($filter['membership_tier_id'])) {
            $query->whereHas('member', fn (Builder $q) => $q->where('membership_tier_id', $filter['membership_tier_id']));
        }

        if (! empty($filter['expiring_within_days'])) {
            $days = (int) $filter['expiring_within_days'];
            $query->whereHas('member', fn (Builder $q) => $q
                ->where('status', MemberStatus::Active)
                ->whereBetween('expires_at', [now(), now()->addDays($days)]));
        }

        if (! empty($filter['all_active_members']) && $filter['all_active_members']) {
            $query->whereHas('member', fn (Builder $q) => $q->where('status', MemberStatus::Active));
        }

        if (! empty($filter['user_ids']) && is_array($filter['user_ids'])) {
            $query->whereIn('id', $filter['user_ids']);
        }

        return $query;
    }

    /** @param array<string, mixed> $filter */
    public function count(array $filter): int
    {
        return $this->resolve($filter)->count();
    }

    /** @param array<string, mixed> $filter */
    public function getRecipients(array $filter, NotificationChannel $channel): Builder
    {
        $query = $this->resolve($filter);

        if ($channel === NotificationChannel::Email) {
            $query->whereNotNull('email');
        }

        if ($channel === NotificationChannel::Sms) {
            $query->whereNotNull('phone');
        }

        return $query;
    }
}
