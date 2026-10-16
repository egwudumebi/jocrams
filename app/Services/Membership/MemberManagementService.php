<?php

namespace App\Services\Membership;

use App\Enums\MemberStatus;
use App\Enums\UserStatus;
use App\Models\Member;
use App\Models\MembershipTier;
use App\Models\User;
use App\Notifications\MembershipApprovedNotification;
use App\Services\Admin\ActivityLogService;
use App\Services\Credentials\MembershipCredentialPackService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MemberManagementService
{
    public function __construct(
        private readonly MembershipCredentialPackService $credentialPack,
        private readonly ActivityLogService $activityLog,
    ) {}

    /** @return array{member: Member, password_reset_sent: bool} */
    public function addMember(array $data, User $admin): array
    {
        return DB::transaction(function () use ($data, $admin) {
            $tier = MembershipTier::query()->findOrFail($data['membership_tier_id']);
            $organization = strtolower((string) ($data['organization'] ?? 'sicama'));
            if ($organization !== 'sicama') {
                $organization = 'sicama';
            }

            $existingUser = User::query()->where('email', $data['email'])->first();
            if ($existingUser?->member) {
                throw ValidationException::withMessages([
                    'email' => ['This user is already a member.'],
                ]);
            }

            $createdUser = false;
            $user = $existingUser;

            if (! $user) {
                $user = User::query()->create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'] ?? null,
                    'password' => Str::random(40),
                    'status' => UserStatus::Active,
                    'email_verified_at' => now(),
                    'has_changed_password' => false,
                ]);
                $createdUser = true;
            } else {
                $user->update([
                    'name' => $data['name'],
                    'phone' => $data['phone'] ?? $user->phone,
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ]);
            }

            $cardRole = trim((string) ($data['card_role'] ?? ''));
            if ($cardRole === '') {
                $cardRole = $organization === 'sicama' ? 'Member' : 'Member';
            }

            $metadata = [
                'organization' => $organization,
                'card_role' => $cardRole,
                'created_by_admin' => $admin->uuid,
            ];

            $member = Member::query()->create([
                'user_id' => $user->id,
                'membership_tier_id' => $tier->id,
                'membership_number' => $this->generateMembershipNumber($organization),
                'status' => MemberStatus::Active,
                'joined_at' => now(),
                'expires_at' => $data['expires_at'] ?? now()->addYear(),
                'approved_at' => now(),
                'approved_by' => $admin->id,
                'metadata' => $metadata,
            ]);

            $this->credentialPack->issuePack(
                $member->fresh(['user.profile', 'tier']),
                'sicama',
            );
            $user->notify(new MembershipApprovedNotification($member->fresh('tier')));

            $sendReset = (bool) ($data['send_password_reset'] ?? true);
            $passwordResetSent = false;

            if ($createdUser && $sendReset) {
                Password::sendResetLink(['email' => $user->email]);
                $passwordResetSent = true;
            }

            $this->activityLog->log($admin, 'members.add', properties: [
                'member_uuid' => $member->uuid,
                'email' => $user->email,
                'organization' => $organization,
            ]);

            return [
                'member' => $member->fresh(['user', 'tier', 'credentials']),
                'password_reset_sent' => $passwordResetSent,
            ];
        });
    }

    public function deactivate(Member $member, User $admin): Member
    {
        if ($member->status === MemberStatus::Suspended) {
            throw ValidationException::withMessages([
                'member' => ['Member is already deactivated.'],
            ]);
        }

        $member->update(['status' => MemberStatus::Suspended]);

        $this->activityLog->log($admin, 'members.deactivate', properties: [
            'member_uuid' => $member->uuid,
        ]);

        return $member->fresh(['user', 'tier']);
    }

    public function reactivate(Member $member, User $admin): Member
    {
        if ($member->status === MemberStatus::Active && $member->isActive()) {
            throw ValidationException::withMessages([
                'member' => ['Member is already active.'],
            ]);
        }

        if ($member->expires_at !== null && $member->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'member' => ['Cannot reactivate an expired membership. Renew first.'],
            ]);
        }

        $member->update(['status' => MemberStatus::Active]);

        $this->activityLog->log($admin, 'members.reactivate', properties: [
            'member_uuid' => $member->uuid,
        ]);

        return $member->fresh(['user', 'tier']);
    }

    private function generateMembershipNumber(string $organization = 'sicama'): string
    {
        return $this->generateSicamaMembershipNumber();
    }

    private function generateSicamaMembershipNumber(): string
    {
        $year = (int) now()->format('Y');
        $nextYearShort = substr((string) ($year + 1), -2);
        $period = "{$year}-{$nextYearShort}";
        $prefix = "SICAMA {$period}/";

        $latest = Member::query()
            ->where('membership_number', 'like', $prefix.'%')
            ->orderByDesc('membership_number')
            ->value('membership_number');

        $next = 1;
        if (is_string($latest) && preg_match('/\/(\d+)$/', $latest, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        do {
            $number = $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $next++;
        } while (Member::query()->where('membership_number', $number)->exists());

        return $number;
    }
}
