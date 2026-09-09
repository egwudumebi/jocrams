<?php

namespace App\Services\Support;

use App\Enums\SupportMessageSource;
use App\Enums\SupportMessageStatus;
use App\Models\ContactInquiry;
use App\Models\User;

class SupportInboxService
{
    /** @param array<string, mixed> $data */
    public function create(array $data, ?User $user = null, SupportMessageSource $source = SupportMessageSource::Contact): ContactInquiry
    {
        return ContactInquiry::query()->create([
            ...$data,
            'user_id' => $user?->id,
            'source' => $source,
            'status' => SupportMessageStatus::New,
        ]);
    }

    public function respond(ContactInquiry $inquiry, User $admin, string $response): ContactInquiry
    {
        $inquiry->update([
            'last_response' => $response,
            'assigned_to' => $admin->id,
            'status' => SupportMessageStatus::Responded,
            'responded_at' => now(),
        ]);

        return $inquiry->fresh(['branch', 'user', 'assignee']);
    }

    public function resolve(ContactInquiry $inquiry, User $admin): ContactInquiry
    {
        $inquiry->update([
            'assigned_to' => $admin->id,
            'status' => SupportMessageStatus::Resolved,
            'resolved_at' => now(),
        ]);

        return $inquiry->fresh(['branch', 'user', 'assignee']);
    }
}
