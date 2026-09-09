<?php

namespace App\Services\Payments;

use App\Enums\PaymentPurpose;
use App\Models\Donation;
use App\Models\EventRegistration;
use App\Models\Payment;
use App\Models\Subscription;
use App\Support\Settings\SiteBranding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class PaymentReceiptPdfService
{
    /** @return array<string, mixed> */
    public function context(Payment $payment): array
    {
        $payment->loadMissing(['user', 'member.tier', 'payable', 'transactions']);

        [$payerName, $payerEmail] = $this->resolvePayer($payment);

        return [
            'payment' => $payment,
            'payerName' => $payerName,
            'payerEmail' => $payerEmail,
            'membershipNumber' => $payment->member?->membership_number,
            'tierName' => $payment->member?->tier?->name,
            'purposeLabel' => $this->purposeLabel($payment->purpose),
            'details' => $this->paymentDetails($payment),
            'gatewayLabel' => ucfirst($payment->gateway->value),
            'siteName' => SiteBranding::siteName(),
            'logoDataUri' => SiteBranding::logoDataUri(),
            'overlayDataUri' => $this->overlayDataUri(),
            'paidAt' => $payment->paid_at ?? now(),
        ];
    }

    public function renderPdf(Payment $payment): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadView('receipts.payment', $this->context($payment))
            ->setPaper('a4', 'portrait');
    }

    private function overlayDataUri(): ?string
    {
        $path = resource_path('images/receipt-overlay.png');

        if (! is_readable($path)) {
            return null;
        }

        $mime = mime_content_type($path) ?: 'image/jpeg';

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
    }

    public function downloadResponse(Payment $payment): Response
    {
        $filename = 'receipt-'.$payment->reference.'.pdf';

        return $this->renderPdf($payment)->download($filename);
    }

    /** @return array{0: string, 1: string} */
    private function resolvePayer(Payment $payment): array
    {
        if ($payment->user) {
            return [$payment->user->name, $payment->user->email];
        }

        if ($payment->payable instanceof Donation) {
            return [$payment->payable->donor_name, $payment->payable->donor_email];
        }

        return [
            (string) ($payment->metadata['guest_name'] ?? 'Guest'),
            (string) ($payment->metadata['guest_email'] ?? ''),
        ];
    }

    private function purposeLabel(PaymentPurpose $purpose): string
    {
        return match ($purpose) {
            PaymentPurpose::Dues => 'Annual membership dues',
            PaymentPurpose::Registration => 'Membership registration',
            PaymentPurpose::EventFee => 'Event registration fee',
            PaymentPurpose::Certification => 'Certification fee',
            PaymentPurpose::Donation => 'Donation',
        };
    }

    private function paymentDetails(Payment $payment): string
    {
        if ($payment->description) {
            return $payment->description;
        }

        $payable = $payment->payable;

        if ($payable instanceof Subscription) {
            return $payable->tier?->name
                ? "Annual dues — {$payable->tier->name}"
                : 'Annual membership dues';
        }

        if ($payable instanceof EventRegistration) {
            return $payable->event?->title
                ? "Event registration — {$payable->event->title}"
                : 'Event registration';
        }

        if ($payable instanceof Donation) {
            return 'Donation to '.SiteBranding::siteName();
        }

        return $this->purposeLabel($payment->purpose);
    }
}
