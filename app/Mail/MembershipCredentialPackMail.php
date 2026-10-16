<?php

namespace App\Mail;

use App\Models\DigitalCredential;
use App\Models\Member;
use App\Support\Settings\SiteBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class MembershipCredentialPackMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  list<DigitalCredential>  $credentials
     */
    public function __construct(
        public Member $member,
        public array $credentials,
        public string $recipientName,
        public string $associationName,
        public ?string $emailBody = null,
        public ?string $emailSubject = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->emailSubject ?? ('Your '.$this->associationName.' membership documents — '.SiteBranding::siteName()),
        );
    }

    public function content(): Content
    {
        $member = $this->member->loadMissing(['tier', 'user']);

        return new Content(
            markdown: 'mail.membership-credential-pack',
            with: [
                'recipientName' => $this->recipientName,
                'emailBody' => $this->emailBody,
                'siteName' => SiteBranding::siteName(),
                'associationName' => $this->associationName,
                'associationFullName' => SiteBranding::parentOrgFullName() ?: $this->associationName,
                'membershipNumber' => $member->membership_number,
                'tierName' => $member->tier?->name ?? 'Member',
                'expiresAt' => $member->expires_at?->format('F j, Y') ?? '—',
                'memberPortalUrl' => url('/member/credentials'),
                'verifyUrl' => url('/verify'),
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        $attachments = [];

        foreach ($this->credentials as $credential) {
            $credential->loadMissing('file');
            $file = $credential->file;

            if (! $file || $file->path === '') {
                continue;
            }

            $disk = $file->disk ?: config('downloads.private_disk', 'local');
            $downloadName = $this->attachmentFilename($credential);

            $attachments[] = Attachment::fromStorageDisk($disk, $file->path)
                ->as($downloadName)
                ->withMime('application/pdf');
        }

        return $attachments;
    }

    private function attachmentFilename(DigitalCredential $credential): string
    {
        $base = match ($credential->type->value) {
            'membership_card' => 'membership-id-card',
            'certificate' => 'membership-certificate',
            'welcome_letter' => 'welcome-letter',
            default => Str::slug($credential->title ?: 'document'),
        };

        $original = $credential->file?->original_filename;
        if (is_string($original) && str_ends_with(strtolower($original), '.pdf')) {
            return $original;
        }

        return $base.'.pdf';
    }
}
