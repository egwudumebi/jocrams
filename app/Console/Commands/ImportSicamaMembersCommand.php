<?php

namespace App\Console\Commands;

use App\Enums\MemberStatus;
use App\Enums\UserStatus;
use App\Models\Member;
use App\Models\MembershipTier;
use App\Models\User;
use App\Services\Credentials\MembershipCredentialPackService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportSicamaMembersCommand extends Command
{
    protected $signature = 'sicama:import-members
        {file? : Path to the SICAMA members Excel file}
        {--sheet=ADDRESS OF MEMEBERS : Worksheet name (falls back to ID CARD NO, then first sheet)}
        {--period=2026-27 : Membership period segment used in card numbers}
        {--expires=2027-12-31 : Card/membership expiry date}
        {--dry-run : Preview without writing}
        {--issue-cards : Issue/regenerate SICAMA ID card, certificate, and welcome letter}
        {--send-resets : Send password reset links to newly created users}';

    protected $description = 'Import SICAMA members from the official ID card Excel list';

    public function handle(MembershipCredentialPackService $credentialPack): int
    {
        $path = $this->argument('file')
            ?: storage_path('app/imports/sicama-members.xlsx');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $tier = MembershipTier::query()->where('slug', 'sicama')->first()
            ?? MembershipTier::query()->where('is_active', true)->orderBy('sort_order')->first()
            ?? MembershipTier::query()->orderBy('sort_order')->first();

        if (! $tier) {
            $this->error('No membership tier found. Seed tiers first.');

            return self::FAILURE;
        }

        $sheetName = (string) $this->option('sheet');
        $period = (string) $this->option('period');
        $expiresAt = \Carbon\Carbon::parse((string) $this->option('expires'))->endOfDay();
        $dryRun = (bool) $this->option('dry-run');
        $issueCards = (bool) $this->option('issue-cards');
        $sendResets = (bool) $this->option('send-resets');

        $workbook = IOFactory::load($path);
        $sheet = $workbook->getSheetByName($sheetName)
            ?? $workbook->getSheetByName('ADDRESS OF MEMEBERS')
            ?? $workbook->getSheetByName('ID CARD NO')
            ?? $workbook->getSheet(0);

        $this->info('Using worksheet: '.$sheet->getTitle());

        $map = $this->detectColumnMap($sheet);
        $this->line('Column map: '.json_encode($map));

        $rows = [];
        foreach ($sheet->getRowIterator(2) as $row) {
            $index = $row->getRowIndex();
            $id = trim((string) $sheet->getCell($map['id'].$index)->getFormattedValue());
            $name = trim((string) $sheet->getCell($map['name'].$index)->getFormattedValue());
            $emailRaw = trim((string) $sheet->getCell($map['email'].$index)->getFormattedValue());
            $phone = $map['phone']
                ? trim((string) $sheet->getCell($map['phone'].$index)->getFormattedValue())
                : '';
            $address = $map['address']
                ? trim((string) $sheet->getCell($map['address'].$index)->getFormattedValue())
                : '';

            if ($id === '' && $name === '') {
                continue;
            }

            if ($name === '') {
                $this->warn("Skipping row {$index}: missing name.");
                continue;
            }

            $emails = $this->splitEmails($emailRaw);
            $email = $emails[0] ?? null;

            if ($email === null) {
                $email = sprintf('sicama-id-%s@members.jocrams.local', str_pad(preg_replace('/\D+/', '', $id) ?: (string) $index, 4, '0', STR_PAD_LEFT));
                $this->warn("Row {$index} ({$name}) has no email — using {$email}");
            }

            $cardNo = str_pad(preg_replace('/\D+/', '', $id) ?: '0', 4, '0', STR_PAD_LEFT);
            $membershipNumber = "SICAMA {$period}/{$cardNo}";

            $rows[] = [
                'card_no' => $cardNo,
                'name' => $this->normalizeName($name),
                'email' => strtolower($email),
                'alt_emails' => array_slice($emails, 1),
                'phone' => $this->normalizePhone($phone),
                'address' => $address !== '' ? $address : null,
                'membership_number' => $membershipNumber,
                'card_role' => $this->resolveCardRole($name),
            ];
        }

        $this->info('Parsed '.count($rows).' SICAMA member row(s).');

        if ($dryRun) {
            $this->table(
                ['Card No', 'Name', 'Email', 'Membership No', 'Role'],
                array_map(fn (array $r) => [
                    $r['card_no'], $r['name'], $r['email'], $r['membership_number'], $r['card_role'],
                ], array_slice($rows, 0, 15)),
            );
            $this->line('Dry run only — showing first 15 rows.');

            return self::SUCCESS;
        }

        $created = 0;
        $updated = 0;
        $cards = 0;

        foreach ($rows as $row) {
            DB::transaction(function () use ($row, $tier, $expiresAt, $issueCards, $sendResets, $credentialService, &$created, &$updated, &$cards) {
                $user = User::query()->where('email', $row['email'])->first();
                $isNewUser = false;

                if (! $user) {
                    $user = User::query()->create([
                        'name' => $row['name'],
                        'email' => $row['email'],
                        'phone' => $row['phone'] ?: null,
                        'password' => Str::random(40),
                        'status' => UserStatus::Active,
                        'email_verified_at' => now(),
                        'has_changed_password' => false,
                    ]);
                    $isNewUser = true;
                    $created++;
                } else {
                    $user->fill([
                        'name' => $row['name'],
                        'status' => UserStatus::Active,
                        'email_verified_at' => $user->email_verified_at ?? now(),
                    ]);
                    if (! empty($row['phone'])) {
                        $user->phone = $row['phone'];
                    }
                    $user->save();
                }

                if (! empty($row['address'])) {
                    $user->profile()->updateOrCreate(
                        ['user_id' => $user->uuid],
                        ['street' => $row['address']],
                    );
                }

                $member = $user->member;
                $metadata = array_merge($member?->metadata ?? [], [
                    'organization' => 'sicama',
                    'card_role' => $row['card_role'],
                    'sicama_card_no' => $row['card_no'],
                    'imported_from' => 'sicama-members.xlsx',
                    'alt_emails' => $row['alt_emails'],
                    'institutional_address' => $row['address'],
                ]);

                if ($member) {
                    // Keep uniqueness if another member already owns this SICAMA number.
                    if (
                        $member->membership_number !== $row['membership_number']
                        && Member::query()
                            ->where('membership_number', $row['membership_number'])
                            ->where('id', '!=', $member->id)
                            ->exists()
                    ) {
                        $this->warn("Membership number {$row['membership_number']} already taken — keeping {$member->membership_number} for {$row['email']}");
                    } else {
                        $member->membership_number = $row['membership_number'];
                    }

                    $member->fill([
                        'membership_tier_id' => $tier->id,
                        'status' => MemberStatus::Active,
                        'expires_at' => $expiresAt,
                        'approved_at' => $member->approved_at ?? now(),
                        'joined_at' => $member->joined_at ?? now(),
                        'metadata' => $metadata,
                    ])->save();
                    $updated++;
                } else {
                    if (Member::query()->where('membership_number', $row['membership_number'])->exists()) {
                        $this->error("Cannot create member for {$row['email']}: number {$row['membership_number']} exists.");

                        return;
                    }

                    $member = Member::query()->create([
                        'user_id' => $user->id,
                        'membership_tier_id' => $tier->id,
                        'membership_number' => $row['membership_number'],
                        'status' => MemberStatus::Active,
                        'joined_at' => now(),
                        'expires_at' => $expiresAt,
                        'approved_at' => now(),
                        'metadata' => $metadata,
                    ]);
                    $updated++;
                }

                if ($issueCards) {
                    $credentialPack->issuePack($member->fresh(['user.profile', 'tier']), 'sicama', deliverEmail: false);
                    $cards++;
                }

                if ($isNewUser && $sendResets && ! str_ends_with($user->email, '@members.jocrams.local')) {
                    \Illuminate\Support\Facades\Password::sendResetLink(['email' => $user->email]);
                }
            });
        }

        $this->info("Users created: {$created}");
        $this->info("Members upserted: {$updated}");
        $this->info("Cards issued: {$cards}");

        return self::SUCCESS;
    }

    /**
     * Detect column letters for both sheet layouts:
     * - ADDRESS OF MEMEBERS: Name | Email | ID CARD NO | Phone | Address | ...
     * - ID CARD NO: ID CARD NO | Name | Email
     *
     * @return array{id: string, name: string, email: string, phone: ?string, address: ?string}
     */
    private function detectColumnMap(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array
    {
        $headers = [];
        foreach (range('A', 'F') as $col) {
            $headers[$col] = Str::lower(trim((string) $sheet->getCell($col.'1')->getFormattedValue()));
        }

        $joined = implode(' ', array_values($headers));

        // Full address sheet
        if (str_contains($joined, 'name') && str_contains($joined, 'id card') && str_contains($joined, 'email')) {
            $idCol = collect($headers)->search(fn (string $h) => str_contains($h, 'id card')) ?: 'C';
            $nameCol = collect($headers)->search(fn (string $h) => $h === 'name' || str_starts_with($h, 'name')) ?: 'A';
            $emailCol = collect($headers)->search(fn (string $h) => str_contains($h, 'email')) ?: 'B';
            $phoneCol = collect($headers)->search(fn (string $h) => str_contains($h, 'phone')) ?: null;
            $addressCol = collect($headers)->search(fn (string $h) => str_contains($h, 'address') || str_contains($h, 'institution')) ?: null;

            return [
                'id' => (string) $idCol,
                'name' => (string) $nameCol,
                'email' => (string) $emailCol,
                'phone' => $phoneCol ? (string) $phoneCol : null,
                'address' => $addressCol ? (string) $addressCol : null,
            ];
        }

        // Compact ID CARD NO sheet (A=id, B=name, C=email)
        return [
            'id' => 'A',
            'name' => 'B',
            'email' => 'C',
            'phone' => null,
            'address' => null,
        ];
    }

    private function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '234') && strlen($digits) >= 13) {
            return '+'.$digits;
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '0') === false) {
            return '+234'.$digits;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return '+234'.substr($digits, 1);
        }

        return $phone;
    }

    /** @return list<string> */
    private function splitEmails(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/[,\s;]+/', $raw) ?: [];

        return array_values(array_filter(array_map(
            fn (string $email) => strtolower(trim($email)),
            $parts,
        ), fn (string $email) => filter_var($email, FILTER_VALIDATE_EMAIL) !== false));
    }

    private function normalizeName(string $name): string
    {
        $name = preg_replace('/\s+/', ' ', trim($name)) ?: $name;

        // Prefer "First Last" when Excel uses "LAST, First".
        if (str_contains($name, ',')) {
            [$last, $first] = array_pad(array_map('trim', explode(',', $name, 2)), 2, '');
            if ($first !== '' && $last !== '') {
                return trim("{$first} {$last}");
            }
        }

        return $name;
    }

    private function resolveCardRole(string $name): string
    {
        $normalized = $this->nameKey($name);

        if ($this->namesMatch($normalized, (string) config('sicama.bot_resolution.signed_by', 'Lawrence Ekwok'))) {
            return 'Chairman, BOT';
        }

        foreach ((array) config('sicama.interim_exco.members', []) as $exco) {
            if ($this->namesMatch($normalized, (string) ($exco['name'] ?? ''))) {
                return 'EXCO Member';
            }
        }

        return 'Member';
    }

    private function nameKey(string $name): string
    {
        return Str::lower(trim(preg_replace('/[^a-z0-9]+/i', ' ', $name) ?? $name));
    }

    private function namesMatch(string $haystackKey, string $candidate): bool
    {
        $candidateKey = $this->nameKey($candidate);
        if ($candidateKey === '') {
            return false;
        }

        if (str_contains($haystackKey, $candidateKey) || str_contains($candidateKey, $haystackKey)) {
            return true;
        }

        $tokens = array_values(array_filter(
            explode(' ', $candidateKey),
            fn (string $token) => strlen($token) > 3 && ! in_array($token, ['doctor', 'prof'], true),
        ));

        if ($tokens === []) {
            return false;
        }

        $hits = 0;
        foreach ($tokens as $token) {
            if (str_contains($haystackKey, $token)) {
                $hits++;
                continue;
            }

            // Soft match close spellings (Sulaiman/Suleiman, Ogubadejo/Ogunbadejo).
            foreach (explode(' ', $haystackKey) as $hayToken) {
                if (strlen($hayToken) > 3 && levenshtein($token, $hayToken) <= 2) {
                    $hits++;
                    break;
                }
            }
        }

        return $hits >= min(2, count($tokens));
    }
}
