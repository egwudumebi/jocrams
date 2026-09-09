<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $siteName }} — {{ $title }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #1e293b;
            background: #ffffff;
            padding: 28px;
        }
        .frame-outer {
            border: 3px solid #0a3d91;
            padding: 8px;
        }
        .frame-inner {
            border: 1px solid #cbd5e1;
            padding: 34px 36px 28px;
            text-align: center;
            background: #ffffff;
        }
        .top-band {
            height: 6px;
            background: #f4b400;
            margin-bottom: 24px;
        }
        .brand-row {
            width: 100%;
            margin-bottom: 18px;
        }
        .brand-row td { vertical-align: middle; }
        .logo {
            width: 54px;
            height: 54px;
            border-radius: 12px;
            background: #eff6ff;
            text-align: center;
        }
        .logo img {
            max-width: 44px;
            max-height: 44px;
            margin-top: 5px;
        }
        .logo-fallback {
            font-size: 24px;
            font-weight: bold;
            line-height: 54px;
            color: #0a3d91;
        }
        .brand-name {
            font-size: 18px;
            font-weight: bold;
            color: #0a3d91;
            text-align: left;
            padding-left: 12px;
        }
        .certificate-kicker {
            font-size: 10px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 8px;
        }
        .certificate-title {
            font-size: 30px;
            font-weight: bold;
            color: #0a3d91;
            margin-bottom: 18px;
        }
        .lead {
            font-size: 13px;
            color: #475569;
            margin-bottom: 10px;
        }
        .member-name {
            font-size: 32px;
            font-weight: bold;
            color: #0f172a;
            margin: 14px 0 18px;
            line-height: 1.2;
        }
        .body-copy {
            font-size: 14px;
            color: #334155;
            line-height: 1.7;
            max-width: 520px;
            margin: 0 auto 22px;
        }
        .facts-table {
            width: 100%;
            max-width: 460px;
            margin: 0 auto 24px;
            border-collapse: collapse;
        }
        .facts-table td {
            border: 1px solid #e2e8f0;
            padding: 10px 12px;
            font-size: 12px;
        }
        .facts-label {
            width: 42%;
            background: #f8fafc;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            font-size: 9px;
            font-weight: bold;
        }
        .facts-value {
            color: #0f172a;
            font-weight: bold;
        }
        .mono {
            font-family: DejaVu Sans Mono, monospace;
        }
        .bottom-row {
            width: 100%;
            margin-top: 8px;
        }
        .bottom-row td {
            vertical-align: bottom;
            width: 50%;
            padding-top: 10px;
        }
        .signature-block {
            text-align: left;
            padding-right: 16px;
        }
        .signature-line {
            border-top: 1px solid #94a3b8;
            width: 180px;
            margin-top: 36px;
            padding-top: 6px;
            font-size: 10px;
            color: #64748b;
        }
        .qr-block {
            text-align: right;
        }
        .qr-box {
            display: inline-block;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 8px;
            background: #f8fafc;
        }
        .qr-box img {
            width: 110px;
            height: 110px;
        }
        .qr-caption {
            margin-top: 6px;
            font-size: 9px;
            color: #64748b;
        }
        .issued {
            margin-top: 18px;
            font-size: 10px;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="frame-outer">
        <div class="frame-inner">
            <div class="top-band"></div>

            <table class="brand-row">
                <tr>
                    <td style="width: 54px;">
                        <div class="logo">
                            @if ($logoDataUri)
                                <img src="{{ $logoDataUri }}" alt="{{ $siteName }} logo">
                            @else
                                <div class="logo-fallback">{{ strtoupper(substr($siteName, 0, 1)) }}</div>
                            @endif
                        </div>
                    </td>
                    <td>
                        <div class="brand-name">{{ $siteName }}</div>
                    </td>
                </tr>
            </table>

            <div class="certificate-kicker">Certificate of membership</div>
            <div class="certificate-title">{{ $title }}</div>

            <div class="lead">This is to certify that</div>
            <div class="member-name">{{ $member->user->name }}</div>

            <div class="body-copy">
                is a verified member of {{ $siteName }} and is entitled to the rights,
                privileges, and recognition associated with the membership tier shown below.
            </div>

            <table class="facts-table">
                <tr>
                    <td class="facts-label">Membership number</td>
                    <td class="facts-value mono">{{ $member->membership_number }}</td>
                </tr>
                <tr>
                    <td class="facts-label">Membership tier</td>
                    <td class="facts-value">{{ $member->tier->name }}</td>
                </tr>
                <tr>
                    <td class="facts-label">Valid until</td>
                    <td class="facts-value">{{ $expiresAt ? $expiresAt->format('F j, Y') : 'No expiry date' }}</td>
                </tr>
            </table>

            <table class="bottom-row">
                <tr>
                    <td class="signature-block">
                        <div class="signature-line">Authorized membership credential</div>
                    </td>
                    <td class="qr-block">
                        <div class="qr-box">
                            <img src="data:{{ $qrMime }};base64,{{ $qrImage }}" alt="Verification QR code">
                            <div class="qr-caption">Scan to verify</div>
                        </div>
                    </td>
                </tr>
            </table>

            <div class="issued">Issued on {{ $issuedAt->format('F j, Y') }}</div>
        </div>
    </div>
</body>
</html>
