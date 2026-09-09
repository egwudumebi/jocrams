<?php

namespace App\Console\Commands;

use App\Enums\MemberStatus;
use App\Models\Member;
use App\Services\Communications\NotificationDispatchService;
use Illuminate\Console\Command;

class SendRenewalRemindersCommand extends Command
{
    protected $signature = 'members:send-renewal-reminders {--days=30 : Days before expiry}';

    protected $description = 'Send renewal reminder notifications to members expiring soon';

    public function handle(NotificationDispatchService $dispatchService): int
    {
        $days = (int) $this->option('days');

        $members = Member::query()
            ->with(['user', 'tier'])
            ->where('status', MemberStatus::Active)
            ->whereBetween('expires_at', [now(), now()->addDays($days)])
            ->get();

        $sent = 0;

        foreach ($members as $member) {
            $log = $dispatchService->sendBySlug('renewal-reminder', $member->user, [
                'days_remaining' => (string) now()->diffInDays($member->expires_at),
                'renewal_amount' => (string) $member->tier->annual_dues,
            ], $member);

            if ($log) {
                $sent++;
            }
        }

        $this->info("Sent {$sent} renewal reminder(s).");

        return self::SUCCESS;
    }
}
