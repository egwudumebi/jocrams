<?php

namespace App\Console\Commands;

use App\Enums\CredentialType;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Models\DigitalCredential;
use App\Models\Member;
use App\Models\Payment;
use App\Services\Credentials\MembershipCredentialPackService;
use Illuminate\Console\Command;

class BackfillMembershipCredentialPacksCommand extends Command
{
    protected $signature = 'membership:backfill-credential-packs
        {--organization=sicama : Association organization key (sicama|sec)}
        {--dry-run : Preview without writing}
        {--force : Reissue packs even when all three credentials already exist}';

    protected $description = 'Renumber paid association members and backfill ID card + certificate + welcome letter packs';

    public function handle(MembershipCredentialPackService $packs): int
    {
        $organization = strtolower((string) $this->option('organization'));
        if (! in_array($organization, ['sicama', 'sec'], true)) {
            $this->error('Organization must be sicama or sec.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $prefix = strtoupper($organization === 'sec' ? 'SEC' : 'SICAMA');

        $paidMemberIds = Payment::query()
            ->whereIn('purpose', [PaymentPurpose::Dues, PaymentPurpose::Registration])
            ->where('status', PaymentStatus::Successful)
            ->whereNotNull('member_id')
            ->distinct()
            ->pluck('member_id');

        $members = Member::query()
            ->with(['user', 'tier', 'credentials'])
            ->whereIn('id', $paidMemberIds)
            ->orderBy('id')
            ->get();

        if ($members->isEmpty()) {
            $this->warn('No paid members found.');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[dry-run] ' : '')."Processing {$members->count()} paid member(s) as {$organization}.");

        $renumbered = 0;
        $issued = 0;
        $skipped = 0;

        foreach ($members as $member) {
            $number = (string) $member->membership_number;
            $needsNumber = ! str_starts_with(strtoupper($number), $prefix);
            $hasPack = $this->hasCompletePack($member);

            if (! $force && ! $needsNumber && $hasPack) {
                $skipped++;
                $this->line("skip #{$member->id} {$number} — already complete");

                continue;
            }

            $this->line(sprintf(
                '%s #%d %s → needs_number=%s needs_pack=%s',
                $dryRun ? 'would-update' : 'update',
                $member->id,
                $number !== '' ? $number : '(none)',
                $needsNumber ? 'yes' : 'no',
                ($force || ! $hasPack) ? 'yes' : 'no',
            ));

            if ($dryRun) {
                if ($needsNumber) {
                    $renumbered++;
                }
                if ($force || ! $hasPack) {
                    $issued++;
                }

                continue;
            }

            if ($needsNumber) {
                $packs->ensureAssociationIdentity($member, $organization);
                $renumbered++;
                $member->refresh();
            } else {
                $metadata = $member->metadata ?? [];
                $metadata['organization'] = $organization;
                $member->update(['metadata' => $metadata]);
            }

            if ($force || ! $this->hasCompletePack($member->fresh('credentials'))) {
                $packs->issuePack($member->fresh(['user.profile', 'tier']), $organization, deliverEmail: false);
                $issued++;
            }
        }

        $this->newLine();
        $this->info("Done. renumbered={$renumbered} packs_issued={$issued} skipped={$skipped}");

        return self::SUCCESS;
    }

    private function hasCompletePack(Member $member): bool
    {
        $types = $member->credentials
            ->whereNull('revoked_at')
            ->pluck('type')
            ->map(fn ($type) => $type instanceof CredentialType ? $type->value : (string) $type)
            ->all();

        return in_array(CredentialType::MembershipCard->value, $types, true)
            && in_array(CredentialType::Certificate->value, $types, true)
            && in_array(CredentialType::WelcomeLetter->value, $types, true);
    }
}
