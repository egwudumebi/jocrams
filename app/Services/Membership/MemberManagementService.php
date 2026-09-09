<?php

namespace App\Services\Membership;

use App\Enums\MemberStatus;
use App\Enums\UserStatus;
use App\Models\Member;
use App\Models\MembershipTier;
use App\Models\User;
use App\Notifications\MembershipApprovedNotification;
use App\Services\Admin\ActivityLogService;
use App\Services\Credentials\CredentialGenerationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MemberManagementService
{
    public function __construct(
        private readonly CredentialGenerationService $credentialService,
        private readonly ActivityLogService $activityLog,
    ) {}

    /** @return array{member: Member, password_reset_sent: bool} */
    public function addMember(array $data, User $admin): array
    {
        return DB::transaction(function () use ($data, $admin) {
            $tier = MembershipTier::query()->findOrFail($data['membership_tier_id']);

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

            $member = Member::query()->create([
                'user_id' => $user->id,
                'membership_tier_id' => $tier->id,
                'membership_number' => $this->generateMembershipNumber(),
                'status' => MemberStatus::Active,
                'joined_at' => now(),
                'expires_at' => $data['expires_at'] ?? now()->addYear(),
                'approved_at' => now(),
                'approved_by' => $admin->id,
            ]);

            $this->credentialService->issueMembershipCard($member);
            $user->notify(new MembershipApprovedNotification($member->fresh('tier')));

            if ($createdUser) {
                Password::sendResetLink(['email' => $user->email]);
            }

            $this->activityLog->log($admin, 'members.add', properties: [
                'member_uuid' => $member->uuid,
                'email' => $user->email,
            ]);

            return [
                'member' => $member->fresh(['user', 'tier', 'credentials']),
                'password_reset_sent' => $createdUser,
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

    private function generateMembershipNumber(): string
    {
        do {
            $number = 'MEM-'.strtoupper(Str::random(8));
        } while (Member::query()->where('membership_number', $number)->exists());

        return $number;
    }
}
