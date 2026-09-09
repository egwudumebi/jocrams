<?php

namespace App\Jobs;

use App\Models\BulkCampaignRecipient;
use App\Models\NotificationTemplate;
use App\Services\Communications\NotificationDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendCampaignMessageJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $recipientId) {}

    public function handle(NotificationDispatchService $dispatchService): void
    {
        $recipient = BulkCampaignRecipient::query()
            ->with(['user', 'campaign'])
            ->findOrFail($this->recipientId);

        if ($recipient->status !== 'pending') {
            return;
        }

        try {
            $campaign = $recipient->campaign;
            $template = new NotificationTemplate([
                'name' => $campaign->name,
                'channel' => $campaign->channel,
                'subject' => $campaign->subject,
                'body' => $campaign->body,
            ]);

            $dispatchService->sendFromTemplate($template, $recipient->user, notifiable: $campaign);

            $recipient->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (\Throwable $e) {
            $recipient->update(['status' => 'failed', 'error' => $e->getMessage()]);
        }
    }
}
