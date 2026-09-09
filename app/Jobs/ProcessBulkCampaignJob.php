<?php

namespace App\Jobs;

use App\Enums\BulkCampaignStatus;
use App\Models\BulkCampaign;
use App\Services\Communications\BulkMessagingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;

class ProcessBulkCampaignJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public BulkCampaign $campaign) {}

    public function handle(BulkMessagingService $bulkMessaging): void
    {
        if ($this->campaign->status === BulkCampaignStatus::Cancelled) {
            return;
        }

        $bulkMessaging->prepareRecipients($this->campaign->fresh());
        $campaignId = $this->campaign->id;

        $recipientIds = $this->campaign->fresh()
            ->recipients()
            ->where('status', 'pending')
            ->pluck('id');

        if ($recipientIds->isEmpty()) {
            BulkCampaign::query()->whereKey($campaignId)->update([
                'status' => BulkCampaignStatus::Completed,
                'completed_at' => now(),
            ]);

            return;
        }

        $jobs = $recipientIds->map(fn (int $id) => new SendCampaignMessageJob($id))->all();

        Bus::batch($jobs)
            ->name("bulk-campaign-{$this->campaign->uuid}")
            ->finally(function () use ($campaignId): void {
                $campaign = BulkCampaign::query()->find($campaignId);

                if (! $campaign) {
                    return;
                }

                $campaign->update([
                    'status' => BulkCampaignStatus::Completed,
                    'completed_at' => now(),
                    'sent_count' => $campaign->recipients()->where('status', 'sent')->count(),
                    'failed_count' => $campaign->recipients()->where('status', 'failed')->count(),
                ]);
            })
            ->dispatch();
    }
}
