<?php

namespace App\Mail;

use App\Models\ContactInquiry;
use App\Support\Settings\SiteBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactInquiryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ContactInquiry $inquiry) {}

    public function envelope(): Envelope
    {
        $siteName = SiteBranding::siteName();

        return new Envelope(
            subject: '['.$siteName.' Contact] '.$this->inquiry->subject,
            replyTo: [
                new Address($this->inquiry->email, $this->inquiry->name),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.contact-inquiry',
            with: [
                'siteName' => SiteBranding::siteName(),
                'inquiry' => $this->inquiry,
            ],
        );
    }
}
