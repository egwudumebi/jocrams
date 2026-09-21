<?php

namespace App\Services\Support;

use App\Enums\SupportMessageSource;
use App\Enums\SupportMessageStatus;
use App\Mail\ContactInquiryMail;
use App\Models\ContactInquiry;
use App\Models\User;
use App\Support\Org\ContactInbox;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SupportInboxService
{
    /** @param array<string, mixed> $data */
    public function create(array $data, ?User $user = null, SupportMessageSource $source = SupportMessageSource::Contact): ContactInquiry
    {
        $inquiry = ContactInquiry::query()->create([
            ...$data,
            'user_id' => $user?->id,
            'source' => $source,
            'status' => SupportMessageStatus::New,
        ]);

        $this->notifyInbox($inquiry);

        return $inquiry;
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

    private function notifyInbox(ContactInquiry $inquiry): void
    {
        $to = ContactInbox::email();

        try {
            Mail::to($to)->send(new ContactInquiryMail($inquiry));
        } catch (\Throwable $e) {
            Log::error('Failed to send contact inquiry email.', [
                'inquiry_uuid' => $inquiry->uuid,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
