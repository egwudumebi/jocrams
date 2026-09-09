<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $siteName }} — Membership Card</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #1e293b;
            background: #f1f5f9;
            padding: 36px 28px;
        }
        .page-title {
            text-align: center;
            font-size: 11px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 18px;
        }
        .card-shell {
            width: 100%;
            max-width: 620px;
            margin: 0 auto;
            border: 1px solid #cbd5e1;
            border-radius: 14px;
            overflow: hidden;
            background: #ffffff;
        }
        .card-header {
            background: #0a3d91;
            color: #ffffff;
            padding: 18px 22px;
        }
        .card-header-table { width: 100%; border-collapse: collapse; }
        .card-header-table td { vertical-align: middle; }
        .logo {
            width: 46px;
            height: 46px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.14);
            text-align: center;
        }
        .logo img {
            max-width: 38px;
            max-height: 38px;
            margin-top: 4px;
        }
        .logo-fallback {
            font-size: 22px;
            font-weight: bold;
            line-height: 46px;
            color: #ffffff;
        }
        .org-name {
            font-size: 22px;
            font-weight: bold;
            letter-spacing: 0.4px;
        }
        .org-tagline {
            font-size: 10px;
            color: #dbeafe;
            margin-top: 2px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .card-type {
            text-align: right;
            font-size: 10px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #f4b400;
            font-weight: bold;
        }
        .gold-rule {
            height: 4px;
            background: #f4b400;
        }
        .card-body {
            padding: 22px 24px 18px;
        }
        .body-table { width: 100%; border-collapse: collapse; }
        .body-table td { vertical-align: top; }
        .details { width: 62%; padding-right: 16px; }
        .member-intro-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .member-intro-table td { vertical-align: middle; }
        .member-photo {
            width: 68px;
            height: 68px;
            border-radius: 999px;
            overflow: hidden;
            border: 2px solid #e2e8f0;
            background: #f1f5f9;
            text-align: center;
        }
        .member-photo img {
            width: 68px;
            height: 68px;
            object-fit: cover;
        }
        .member-photo-fallback {
            font-size: 24px;
            font-weight: bold;
            line-height: 64px;
            color: #64748b;
        }
        .label {
            font-size: 9px;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 4px;
        }
        .value-name {
            font-size: 24px;
            font-weight: bold;
            color: #0f172a;
            line-height: 1.2;
            margin-bottom: 14px;
        }
        .value {
            font-size: 13px;
            font-weight: bold;
            color: #0a3d91;
            margin-bottom: 12px;
        }
        .value-muted {
            font-size: 12px;
            color: #334155;
            margin-bottom: 12px;
        }
        .mono {
            font-family: DejaVu Sans Mono, monospace;
            letter-spacing: 0.6px;
        }
        .qr-wrap {
            width: 38%;
            text-align: center;
            padding-top: 4px;
        }
        .qr-box {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 10px;
            background: #f8fafc;
        }
        .qr-box img {
            width: 132px;
            height: 132px;
        }
        .qr-caption {
            margin-top: 8px;
            font-size: 9px;
            color: #64748b;
            line-height: 1.4;
        }
        .card-footer {
            border-top: 1px solid #e2e8f0;
            background: #f8fafc;
            padding: 10px 24px;
            font-size: 9px;
            color: #64748b;
            text-align: center;
        }
        .status-pill {
            display: inline-block;
            background: #dcfce7;
            color: #166534;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            padding: 4px 8px;
            border-radius: 999px;
            margin-top: 6px;
        }
    </style>
</head>
<body>
    <div class="page-title">Official Digital Membership Credential</div>

    <div class="card-shell">
        <div class="card-header">
            <table class="card-header-table">
                <tr>
                    <td style="width: 56px;">
                        <div class="logo">
                            @if ($logoDataUri)
                                <img src="{{ $logoDataUri }}" alt="{{ $siteName }} logo">
                            @else
                                <div class="logo-fallback">{{ strtoupper(substr($siteName, 0, 1)) }}</div>
                            @endif
                        </div>
                    </td>
                    <td style="padding-left: 12px;">
                        <div class="org-name">{{ $siteName }}</div>
                        <div class="org-tagline">Verified Member Credential</div>
                    </td>
                    <td class="card-type">Membership Card</td>
                </tr>
            </table>
        </div>

        <div class="gold-rule"></div>

        <div class="card-body">
            <table class="body-table">
                <tr>
                    <td class="details">
                        <table class="member-intro-table">
                            <tr>
                                <td style="width: 80px; padding-right: 12px;">
                                    <div class="member-photo">
                                        @if ($profileImageDataUri)
                                            <img src="{{ $profileImageDataUri }}" alt="{{ $member->user->name }}">
                                        @else
                                            <div class="member-photo-fallback">{{ strtoupper(substr($member->user->name, 0, 1)) }}</div>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="label">Member name</div>
                                    <div class="value-name" style="margin-bottom: 0;">{{ $member->user->name }}</div>
                                </td>
                            </tr>
                        </table>

                        <div class="label">Membership number</div>
                        <div class="value mono">{{ $member->membership_number }}</div>

                        <div class="label">Membership tier</div>
                        <div class="value-muted">{{ $member->tier->name }}</div>

                        <div class="label">Valid until</div>
                        <div class="value-muted">
                            {{ $expiresAt ? $expiresAt->format('F j, Y') : 'No expiry date' }}
                        </div>

                        <div class="status-pill">Active member</div>
                    </td>
                    <td class="qr-wrap">
                        <div class="qr-box">
                            <img src="data:{{ $qrMime }};base64,{{ $qrImage }}" alt="Verification QR code">
                            <div class="qr-caption">Scan to verify<br>this credential online</div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="card-footer">
            Issued {{ $issuedAt->format('F j, Y') }} · Credential ref {{ $member->membership_number }}
        </div>
    </div>
</body>
</html>
