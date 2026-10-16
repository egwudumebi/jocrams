<x-mail::message>
# Your membership documents are ready

Dear {{ $recipientName }},

@if ($emailBody)
{{ $emailBody }}

Your official **{{ $associationName }}** membership documents are attached to this email as PDF files.
@else
Your **{{ $associationName }}** membership is now fully confirmed. Welcome to **{{ $associationFullName }}**.

We have attached your official membership pack to this email:

- **Membership ID card** (with QR verification)
- **Membership certificate**
- **Welcome letter** from the association leadership

You can also download these anytime from your member portal.
@endif

<x-mail::panel>
**Membership number:** {{ $membershipNumber }}

**Tier:** {{ $tierName }}

**Valid until:** {{ $expiresAt }}

**Verify documents:** [{{ $verifyUrl }}]({{ $verifyUrl }})
</x-mail::panel>

<x-mail::button :url="$memberPortalUrl">
View credentials in portal
</x-mail::button>

Keep these PDFs for your records. If any attachment fails to open, sign in to the portal and download them again.

Thanks,<br>
{{ $siteName }} · {{ $associationName }}
</x-mail::message>
