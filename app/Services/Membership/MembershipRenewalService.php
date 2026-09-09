<?php

namespace App\Services\Membership;

use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\SystemSetting;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class MembershipRenewalService
{
    public function renewalWindowDays(): int
    {
        $days = (int) SystemSetting::query()
            ->where('group_name', 'membership')
            ->where('setting_key', 'renewal_reminder_days')
            ->value('setting_value');

        return max(1, $days ?: 30);
    }

    public function canRenew(Member $member): bool
    {
        if ($member->status !== MemberStatus::Active) {
            return true;
        }

        if ($member->expires_at === null) {
            return false;
        }

        if ($member->expires_at->isPast()) {
            return true;
        }

        return now()->gte($this->renewalOpensAt($member));
    }

    public function renewalOpensAt(Member $member): ?Carbon
    {
        if ($member->expires_at === null) {
            return null;
        }

        return $member->expires_at->copy()->subDays($this->renewalWindowDays());
    }

    public function assertCanRenew(Member $member): void
    {
        if ($this->canRenew($member)) {
            return;
        }

        $opensAt = $this->renewalOpensAt($member);

        throw ValidationException::withMessages([
            'member' => [
                $opensAt
                    ? 'Renewal opens on '.$opensAt->toFormattedDateString().'. Your membership is still active until '.$member->expires_at?->toFormattedDateString().'.'
                    : 'Your membership is still active. Renewal is not available yet.',
            ],
        ]);
    }

    /** @return array<string, mixed> */
    public function present(Member $member): array
    {
        $opensAt = $this->renewalOpensAt($member);

        return [
            'can_renew' => $this->canRenew($member),
            'renewal_window_days' => $this->renewalWindowDays(),
            'renewal_opens_at' => $opensAt?->toIso8601String(),
            'expires_at' => $member->expires_at?->toIso8601String(),
        ];
    }
}
