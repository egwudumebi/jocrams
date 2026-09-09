<?php

namespace App\Support\Payments;

use App\Enums\PaymentPurpose;
use App\Models\EventRegistration;
use App\Models\Payment;

class PaymentReturnResolver
{
    public function resolve(Payment $payment): string
    {
        $payment->loadMissing('payable');

        if ($payment->purpose === PaymentPurpose::EventFee) {
            $eventUuid = $payment->metadata['event_uuid'] ?? null;

            if (! $eventUuid && $payment->payable instanceof EventRegistration) {
                $payment->payable->loadMissing('event');
                $eventUuid = $payment->payable->event?->uuid;
            }

            if ($eventUuid) {
                return $payment->user_id
                    ? "/member/events/{$eventUuid}"
                    : "/events/{$eventUuid}";
            }
        }

        if ($payment->purpose === PaymentPurpose::Dues) {
            return '/member/payments';
        }

        if ($payment->purpose === PaymentPurpose::JournalSubmissionFee) {
            $submissionUuid = $payment->metadata['submission_uuid'] ?? null;

            if ($submissionUuid) {
                return "/member/journal/{$submissionUuid}";
            }
        }

        return '/payments/return';
    }

    public function callbackUrl(Payment $payment): string
    {
        $path = $this->resolve($payment);

        return rtrim((string) config('app.url'), '/').$path;
    }
}
