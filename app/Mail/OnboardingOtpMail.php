<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OnboardingOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private readonly string $firstName,
        private readonly string $otp,
        private readonly int $ttlMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your membership verification code');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.onboarding-otp',
            with: [
                'firstName' => $this->firstName,
                'otp' => $this->otp,
                'ttlMinutes' => $this->ttlMinutes,
            ],
        );
    }
}
