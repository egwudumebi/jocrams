<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Membership Verification Code</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111827;">
<p>Hello {{ $firstName }},</p>
<p>Your membership verification code is:</p>
<p style="font-size: 28px; font-weight: 700; letter-spacing: 4px;">{{ $otp }}</p>
<p>This code expires in {{ $ttlMinutes }} minutes.</p>
<p>If you did not request this, please ignore this email.</p>
</body>
</html>
