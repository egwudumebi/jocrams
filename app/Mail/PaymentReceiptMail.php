<?php

namespace App\Mail;

use App\Models\Payment;
use App\Services\Payments\PaymentReceiptPdfService;
use App\Support\Settings\SiteBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentReceiptMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Payment $payment,
        public string $recipientName,
        public ?string $emailBody = null,
        public ?string $emailSubject = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->emailSubject ?? ('Payment Receipt — '.SiteBranding::siteName()),
        );
    }

    public function content(): Content
    {
        $context = app(PaymentReceiptPdfService::class)->context($this->payment);

        return new Content(
            markdown: 'mail.payment-receipt',
            with: [
                'recipientName' => $this->recipientName,
                'emailBody' => $this->emailBody,
                'siteName' => SiteBranding::siteName(),
                'currency' => $this->payment->currency,
                'formattedAmount' => number_format((float) $this->payment->amount, 2),
                'reference' => $this->payment->reference,
                'purposeLabel' => $context['purposeLabel'],
                'details' => $context['details'],
                'paidAt' => ($this->payment->paid_at ?? now())->format('F j, Y g:i A'),
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        $pdf = app(PaymentReceiptPdfService::class)->renderPdf($this->payment);

        return [
            Attachment::fromData(
                fn (): string => $pdf->output(),
                'receipt-'.$this->payment->reference.'.pdf',
            )->withMime('application/pdf'),
        ];
    }
}
