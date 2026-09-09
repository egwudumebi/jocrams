<?php

namespace App\Services\Payments;

use App\Enums\NotificationChannel;
use App\Enums\NotificationLogStatus;
use App\Mail\PaymentReceiptMail;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\Payment;
use App\Models\User;
use App\Services\Communications\TemplateRendererService;
use App\Support\Settings\SiteBranding;
use Illuminate\Support\Facades\Mail;

class PaymentReceiptDeliveryService
{
    public function __construct(
        private readonly TemplateRendererService $renderer,
    ) {}

    public function send(Payment $payment): void
    {
        $payment->loadMissing(['user', 'member.tier']);

        [$email, $name] = $this->resolveRecipient($payment);

        if ($email === '') {
            return;
        }

        [$subject, $body] = $this->resolveEmailContent($payment, $name);

        try {
            Mail::to($email)->send(new PaymentReceiptMail(
                payment: $payment,
                recipientName: $name,
                emailBody: $body,
                emailSubject: $subject,
            ));

            $this->logDelivery($payment, $email, $subject, $body, NotificationLogStatus::Sent);
        } catch (\Throwable $exception) {
            $this->logDelivery($payment, $email, $subject, $body, NotificationLogStatus::Failed, $exception->getMessage());

            throw $exception;
        }
    }

    /** @return array{0: string, 1: string} */
    private function resolveRecipient(Payment $payment): array
    {
        if ($payment->user) {
            return [$payment->user->email, $payment->user->name];
        }

        $payable = $payment->payable;

        if ($payable instanceof \App\Models\Donation) {
            return [$payable->donor_email, $payable->donor_name];
        }

        return [
            (string) ($payment->metadata['guest_email'] ?? ''),
            (string) ($payment->metadata['guest_name'] ?? 'Guest'),
        ];
    }

    /** @return array{0: string, 1: string|null} */
    private function resolveEmailContent(Payment $payment, string $recipientName): array
    {
        $template = NotificationTemplate::query()
            ->where('slug', 'payment-receipt')
            ->where('is_active', true)
            ->first();

        $defaultSubject = 'Payment Receipt — '.SiteBranding::siteName();

        if (! $template || $template->channel !== NotificationChannel::Email) {
            return [$defaultSubject, null];
        }

        $variables = [
            'name' => $recipientName,
            'amount' => number_format((float) $payment->amount, 2),
            'currency' => $payment->currency,
            'reference' => $payment->reference,
            'purpose' => app(PaymentReceiptPdfService::class)->context($payment)['purposeLabel'],
            'app_name' => SiteBranding::siteName(),
            'paid_at' => ($payment->paid_at ?? now())->format('F j, Y g:i A'),
        ];

        if ($payment->user instanceof User) {
            $variables = array_merge([
                'email' => $payment->user->email,
                'member_number' => $payment->user->member?->membership_number ?? '',
                'tier_name' => $payment->user->member?->tier?->name ?? '',
            ], $variables);
        }

        $rendered = $this->renderer->render($template, $variables);

        return [
            $rendered['subject'] ?? $defaultSubject,
            $rendered['body'] ?? null,
        ];
    }

    private function logDelivery(
        Payment $payment,
        string $email,
        string $subject,
        ?string $body,
        NotificationLogStatus $status,
        ?string $error = null,
    ): void {
        NotificationLog::query()->create([
            'notification_template_id' => NotificationTemplate::query()
                ->where('slug', 'payment-receipt')
                ->value('id'),
            'notifiable_type' => $payment->getMorphClass(),
            'notifiable_id' => $payment->getKey(),
            'channel' => NotificationChannel::Email,
            'recipient' => $email,
            'subject' => $subject,
            'body' => $body ?? 'Branded payment receipt email with PDF attachment.',
            'status' => $status,
            'sent_at' => $status === NotificationLogStatus::Sent ? now() : null,
            'error' => $error,
        ]);
    }
}
