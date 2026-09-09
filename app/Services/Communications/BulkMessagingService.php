<?php

namespace App\Services\Communications;

use App\Enums\BulkCampaignStatus;
use App\Jobs\ProcessBulkCampaignJob;
use App\Models\BulkCampaign;
use App\Models\BulkCampaignRecipient;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BulkMessagingService
{
    public function __construct(private readonly AudienceResolverService $audienceResolver) {}

    /** @param array<string, mixed> $data */
    public function createDraft(User $creator, array $data): BulkCampaign
    {
        return BulkCampaign::query()->create([
            'created_by' => $creator->id,
            'name' => $data['name'],
            'channel' => $data['channel'],
            'subject' => $data['subject'] ?? null,
            'body' => $data['body'],
            'audience_filter' => $data['audience_filter'] ?? [],
            'status' => BulkCampaignStatus::Draft,
            'scheduled_at' => $data['scheduled_at'] ?? null,
        ]);
    }

    public function schedule(BulkCampaign $campaign): BulkCampaign
    {
        if ($campaign->status !== BulkCampaignStatus::Draft) {
            throw ValidationException::withMessages(['campaign' => ['Only draft campaigns can be scheduled.']]);
        }

        $count = $this->audienceResolver->count($campaign->audience_filter ?? []);

        if ($count === 0) {
            throw ValidationException::withMessages(['audience_filter' => ['No recipients match the audience filter.']]);
        }

        $campaign->update([
            'status' => BulkCampaignStatus::Scheduled,
            'total_recipients' => $count,
        ]);

        ProcessBulkCampaignJob::dispatch($campaign)->delay($campaign->scheduled_at ?? now());

        return $campaign->fresh();
    }

    public function dispatchNow(BulkCampaign $campaign): BulkCampaign
    {
        if (! in_array($campaign->status, [BulkCampaignStatus::Draft, BulkCampaignStatus::Scheduled], true)) {
            throw ValidationException::withMessages(['campaign' => ['Campaign cannot be dispatched.']]);
        }

        ProcessBulkCampaignJob::dispatchSync($campaign);

        return $campaign->fresh();
    }

    public function prepareRecipients(BulkCampaign $campaign): void
    {
        DB::transaction(function () use ($campaign): void {
            $campaign->update([
                'status' => BulkCampaignStatus::Sending,
                'started_at' => now(),
            ]);

            $users = $this->audienceResolver
                ->getRecipients($campaign->audience_filter ?? [], $campaign->channel)
                ->get();

            foreach ($users as $user) {
                BulkCampaignRecipient::query()->firstOrCreate([
                    'bulk_campaign_id' => $campaign->id,
                    'user_id' => $user->id,
                ]);
            }

            $campaign->update(['total_recipients' => $users->count()]);
        });
    }

    public function cancel(BulkCampaign $campaign): BulkCampaign
    {
        if (in_array($campaign->status, [BulkCampaignStatus::Completed, BulkCampaignStatus::Cancelled], true)) {
            throw ValidationException::withMessages(['campaign' => ['Campaign cannot be cancelled.']]);
        }

        $campaign->update(['status' => BulkCampaignStatus::Cancelled]);

        return $campaign->fresh();
    }
}
