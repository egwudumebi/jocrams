<?php

namespace App\Services\Credentials;

use App\Enums\CredentialType;
use App\Models\DigitalCredential;
use App\Models\Member;
use Illuminate\Support\Facades\DB;

class MembershipCredentialPackService
{
    public function __construct(
        private readonly CredentialGenerationService $credentials,
    ) {}

    /**
     * Ensure association identity, then issue ID card + certificate + welcome letter
     * (same membership number on all documents).
     *
     * @return list<DigitalCredential>
     */
    public function issuePack(Member $member, string $organization = 'sicama', bool $deliverEmail = true): array
    {
        return DB::transaction(function () use ($member, $organization, $deliverEmail) {
            $member = $this->ensureAssociationIdentity($member->fresh(['user.profile', 'tier']), $organization);

            $this->revokeActiveCredentials($member, [
                CredentialType::MembershipCard,
                CredentialType::Certificate,
                CredentialType::WelcomeLetter,
            ]);

            $member = $member->fresh(['user.profile', 'tier']);

            $certificateTitle = match ($organization) {
                'sicama' => 'SICAMA Membership Certificate',
                'sec' => 'SEC Membership Certificate',
                default => 'Membership Certificate',
            };

            $issued = [
                $this->credentials->issueMembershipCard($member),
                $this->credentials->issueCertificate($member, $certificateTitle),
            ];

            if (in_array($organization, ['sicama', 'sec'], true)) {
                $issued[] = $this->credentials->issueWelcomeLetter($member);
            }

            $metadata = $member->metadata ?? [];
            unset($metadata['awaiting_membership_payment']);
            $member->update(['metadata' => $metadata]);

            if ($deliverEmail) {
                app(MembershipCredentialPackDeliveryService::class)->send(
                    $member->fresh(['user.profile', 'tier']),
                    $issued,
                    $organization,
                );
            }

            return $issued;
        });
    }

    public function ensureAssociationIdentity(Member $member, string $organization = 'sicama'): Member
    {
        $metadata = $member->metadata ?? [];
        $metadata['organization'] = $organization;
        if (blank(data_get($metadata, 'card_role'))) {
            $metadata['card_role'] = 'Member';
        }

        $updates = ['metadata' => $metadata];

        $number = (string) $member->membership_number;
        $prefix = strtoupper($organization === 'sec' ? 'SEC' : 'SICAMA');
        $needsAssociationNumber = in_array($organization, ['sicama', 'sec'], true)
            && ! str_starts_with(strtoupper($number), $prefix);

        if ($needsAssociationNumber) {
            $updates['membership_number'] = $this->nextAssociationMembershipNumber($organization);
        }

        $member->update($updates);

        return $member->fresh(['user.profile', 'tier']);
    }

    /** @param  list<CredentialType>  $types */
    private function revokeActiveCredentials(Member $member, array $types): void
    {
        DigitalCredential::query()
            ->where('member_id', $member->id)
            ->whereNull('revoked_at')
            ->whereIn('type', array_map(fn (CredentialType $type) => $type->value, $types))
            ->update([
                'revoked_at' => now(),
                'revocation_reason' => 'Reissued as part of membership credential pack',
            ]);
    }

    private function nextAssociationMembershipNumber(string $organization = 'sicama'): string
    {
        $year = (int) now()->format('Y');
        $nextYearShort = substr((string) ($year + 1), -2);
        $period = "{$year}-{$nextYearShort}";
        $label = $organization === 'sec' ? 'SEC' : 'SICAMA';
        $prefix = "{$label} {$period}/";

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
