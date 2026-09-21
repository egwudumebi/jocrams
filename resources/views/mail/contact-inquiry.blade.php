<x-mail::message>
# New contact message

A visitor submitted a message through the **{{ $siteName }}** contact form.

<x-mail::panel>
**Name:** {{ $inquiry->name }}

**Email:** {{ $inquiry->email }}

@if ($inquiry->phone)
**Phone:** {{ $inquiry->phone }}

@endif
**Subject:** {{ $inquiry->subject }}
</x-mail::panel>

**Message**

{{ $inquiry->message }}

You can reply directly to this email to respond to **{{ $inquiry->name }}**.

Thanks,<br>
{{ $siteName }}
</x-mail::message>
