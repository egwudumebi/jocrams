<?php

namespace App\Services\Events;

use App\Enums\EventMemberType;
use App\Enums\MemberStatus;
use App\Models\Member;
use Illuminate\Validation\ValidationException;

class EventMembershipVerifier
{
    public function normalizeNumber(string $number): string
    {
        return strtoupper(trim($number));
    }

    public function findActiveByNumber(string $number): ?Member
    {
        $normalized = $this->normalizeNumber($number);

        if ($normalized === '') {
            return null;
        }

        return Member::query()
            ->with(['user', 'tier'])
            ->where('membership_number', $normalized)
            ->where('status', MemberStatus::Active)
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();
    }

    /** @return array{valid: bool, member_name?: string, tier_name?: string} */
    public function presentForPublic(string $number): array
    {
        $member = $this->findActiveByNumber($number);

        if (! $member) {
            return ['valid' => false];
        }

        return [
            'valid' => true,
            'member_name' => $this->maskName($member->user?->name ?? $member->membership_number),
            'tier_name' => $member->tier?->name,
        ];
    }

    public function assertActiveMemberTicket(?string $membershipNumber): Member
    {
        if (! $membershipNumber) {
            throw ValidationException::withMessages([
                'membership_number' => ['Enter your membership number to register with member pricing.'],
            ]);
        }

        $member = $this->findActiveByNumber($membershipNumber);

        if (! $member) {
            throw ValidationException::withMessages([
                'membership_number' => ['We could not find an active membership with that number. Check the number and try again.'],
            ]);
        }

        return $member;
    }

    public function requiresMembershipNumber(?string $memberType): bool
    {
        return $memberType === EventMemberType::Member->value;
    }

    private function maskName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        if ($parts === []) {
            return 'Member';
        }

        $first = $parts[0];
        $last = $parts[count($parts) - 1] ?? '';

        if (count($parts) === 1) {
            return mb_substr($first, 0, 1).str_repeat('•', max(1, mb_strlen($first) - 1));
        }

        return $first.' '.mb_substr($last, 0, 1).'.';
    }
}
