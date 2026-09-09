<x-mail::message>
# Payment Receipt

Dear {{ $recipientName }},

@if ($emailBody)
{{ $emailBody }}

Your official receipt is attached to this email as a PDF for your records.
@else
Thank you — your payment to **{{ $siteName }}** was processed successfully.

Your official receipt is attached to this email as a PDF for your records.
@endif

<x-mail::panel>
**Amount:** {{ $currency }} {{ $formattedAmount }}

**Reference:** {{ $reference }}

**Purpose:** {{ $purposeLabel }}

**Description:** {{ $details }}

**Paid on:** {{ $paidAt }}
</x-mail::panel>

If you have any questions about this payment, reply to this email or contact our support team.

Thanks,<br>
{{ $siteName }}
</x-mail::message>
