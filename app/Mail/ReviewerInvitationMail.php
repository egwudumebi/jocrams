<?php

namespace App\Mail;

use App\Support\Settings\SiteBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReviewerInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $recipientEmail,
        public ?string $temporaryPassword = null,
    ) {}

    public function envelope(): Envelope
    {
        $site = SiteBranding::siteName();

        return new Envelope(
            subject: "{$site}: you have been added as a peer reviewer",
        );
    }

    public function content(): Content
    {
        $site = SiteBranding::siteName();

        return new Content(
            markdown: 'mail.reviewer-invitation',
            with: [
                'recipientName' => $this->recipientName,
                'recipientEmail' => $this->recipientEmail,
                'temporaryPassword' => $this->temporaryPassword,
                'isExistingAccount' => $this->temporaryPassword === null,
                'siteName' => $site,
                'journalName' => SiteBranding::journalFullName() ?: $site,
                'loginUrl' => url('/login'),
            ],
        );
    }
}
