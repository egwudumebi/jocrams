<x-mail::message>
# Peer reviewer access granted

Hello {{ $recipientName }},

You have been added as a **peer reviewer** for **{{ $journalName }}** on {{ $siteName }}.

You can now be assigned manuscripts and will see them in your reviewer queue after you sign in.

@if ($isExistingAccount)
Use your existing account to continue:

<x-mail::panel>
**Login email:** {{ $recipientEmail }}
</x-mail::panel>
@else
A new reviewer account was created for you. Sign in with the details below, then change your password immediately.

<x-mail::panel>
**Login email:** {{ $recipientEmail }}

**Temporary password:** {{ $temporaryPassword }}
</x-mail::panel>
@endif

<x-mail::button :url="$loginUrl">
Sign in to {{ $siteName }}
</x-mail::button>

If you were not expecting this invitation, reply to this email or contact the editorial office.

Thanks,<br>
{{ $siteName }} Editorial Office
</x-mail::message>
