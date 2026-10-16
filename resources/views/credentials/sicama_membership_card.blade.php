<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>SICAMA Membership Card — {{ $member->user->name }}</title>
    <style>
        @page { margin: 0; size: 242.64pt 152.52pt; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: DejaVu Sans, sans-serif; color: #111; font-size: 8px; }
        .page {
            position: relative;
            width: 242.64pt;
            height: 152.52pt;
            overflow: hidden;
            page-break-after: always;
        }
        .page-back { page-break-after: auto; }
        .band-top {
            position: absolute; left: 0; top: 0; right: 0;
            height: 30pt; background: #156082; color: #fff;
        }
        .band-bottom {
            position: absolute; left: 0; bottom: 0; right: 0;
            height: 14pt; background: #156082; color: #fff;
            text-align: center; line-height: 14pt;
            font-size: 7.5pt; font-weight: bold; letter-spacing: 0.3pt;
        }
        .band-bottom-thin { height: 7pt; }
        .logo {
            position: absolute; left: 7pt; top: 4pt;
            width: 22pt; height: 22pt; background: #fff; border-radius: 50%;
            text-align: center; overflow: hidden;
        }
        .logo img { width: 20pt; height: 20pt; margin-top: 1pt; }
        .hdr-text { position: absolute; left: 34pt; top: 3pt; right: 6pt; color: #fff; }
        .org-full {
            font-size: 5.2pt; font-weight: bold; text-transform: uppercase;
            line-height: 1.05; letter-spacing: 0.05pt;
        }
        .org-short {
            font-size: 11pt; font-weight: bold; letter-spacing: 1.2pt; margin-top: 0.5pt;
        }
        .hdr-back .org-short { margin-top: 0; }
        .hdr-back .org-full { margin-top: 1pt; }
        .body {
            position: absolute; left: 0; right: 0; top: 30pt; bottom: 14pt;
            background: #fff; overflow: hidden;
        }
        .body-back { bottom: 7pt; }
        .wm {
            position: absolute; left: -18pt; top: 28pt; width: 300pt;
            font-size: 15pt; font-weight: bold; color: #e6e6e6;
            letter-spacing: 1pt; transform: rotate(-32deg); white-space: nowrap;
        }
        .photo {
            position: absolute; left: 8pt; top: 8pt;
            width: 42pt; height: 42pt; border: 1.1pt solid #156082;
            background: #f3f4f6; text-align: center; overflow: hidden;
        }
        .photo img { width: 42pt; height: 42pt; }
        .photo-ph {
            font-size: 5.5pt; color: #64748b; font-weight: bold;
            line-height: 1.15; padding-top: 12pt;
        }
        .identity {
            position: absolute; left: 58pt; right: 8pt; top: 10pt;
            text-align: center;
        }
        .name { font-size: 10.5pt; font-weight: bold; line-height: 1.12; }
        .role { font-size: 9pt; font-weight: bold; margin-top: 2pt; }
        .meta {
            position: absolute; left: 8pt; right: 8pt; top: 56pt;
            font-size: 7.2pt; line-height: 1.3;
        }
        .bold { font-weight: bold; }
        .meta-line { margin-top: 1pt; }
        .back-title {
            text-align: center; font-size: 9.5pt; font-weight: bold;
            letter-spacing: 0.5pt; text-decoration: underline; margin-top: 4pt;
        }
        .back-copy {
            margin-top: 4pt; font-size: 6pt; line-height: 1.25;
            text-align: center; padding: 0 10pt;
        }
        .back-h { margin-top: 4pt; text-align: center; font-size: 6.4pt; font-weight: bold; }
        .back-c { margin-top: 2pt; text-align: center; font-size: 6pt; line-height: 1.3; }
        .email { color: #156082; text-decoration: underline; }
        .sig-wrap {
            position: absolute; left: 6pt; right: 6pt; bottom: 4pt;
            height: 36pt;
        }
        .sig-col { position: absolute; top: 0; width: 34%; text-align: center; }
        .sig-col-1 { left: 0; }
        .sig-col-2 { left: 34%; }
        .sig-col-3 { left: 68%; width: 32%; }
        .sig-img { height: 16pt; max-width: 70pt; }
        .sig-line { border-top: 0.7pt solid #111; margin: 1pt 5pt 0; }
        .sig-label { font-size: 6.5pt; font-weight: bold; margin-top: 1pt; }
        .qr { width: 28pt; height: 28pt; }
        .qr-cap { font-size: 5.5pt; font-weight: bold; margin-top: 1pt; }
    </style>
</head>
<body>
    <div class="page">
        <div class="band-top">
            <div class="logo">
                @if ($logoDataUri)<img src="{{ $logoDataUri }}" alt="SICAMA">@endif
            </div>
            <div class="hdr-text">
                <div class="org-full">Scholars in Communication and Media Advancement Initiative</div>
                <div class="org-short">SICAMA</div>
            </div>
        </div>
        <div class="body">
            <div class="wm">SICAMA INITIATIVE</div>
            <div class="photo">
                @if ($profileImageDataUri)
                    <img src="{{ $profileImageDataUri }}" alt="{{ $member->user->name }}">
                @else
                    <div class="photo-ph">Your Picture<br>Here</div>
                @endif
            </div>
            <div class="identity">
                <div class="name">{{ $member->user->name }}</div>
                <div class="role">{{ $cardRoleTitle }}</div>
            </div>
            <div class="meta">
                <div class="bold">Membership No:</div>
                <div class="bold">{{ $member->membership_number }}</div>
                <div class="meta-line">Valid Until {{ $validUntilLabel }}</div>
                <div class="meta-line"><span class="bold">E-mail:</span> {{ $member->user->email }}</div>
            </div>
        </div>
        <div class="band-bottom">OFFICIAL SICAMA MEMBERSHIP CARD</div>
    </div>

    <div class="page page-back">
        <div class="band-top">
            <div class="logo">
                @if ($logoDataUri)<img src="{{ $logoDataUri }}" alt="SICAMA">@endif
            </div>
            <div class="hdr-text hdr-back">
                <div class="org-short">SICAMA</div>
                <div class="org-full">Scholars in Communication and Media Advancement Initiative</div>
            </div>
        </div>
        <div class="body body-back">
            <div class="wm">SICAMA INITIATIVE</div>
            <div class="back-title">MEMBERSHIP CARD</div>
            <div class="back-copy">
                This Card remains the property of <strong>SICAMA</strong>
                and is valid only for the period stated on the
                front. If found, please return it to the organization.
            </div>
            <div class="back-h">For Membership Verification &amp; Enquiries</div>
            <div class="back-c">
                E-Mail: <span class="email">merlaw2002@yahoo.com</span><br>
                TEL: +2348035525785, +2348060051572
            </div>
            <div class="sig-wrap">
                <div class="sig-col sig-col-1">
                    @if ($chairmanSignatureDataUri)
                        <img class="sig-img" src="{{ $chairmanSignatureDataUri }}" alt="Chairman">
                    @endif
                    <div class="sig-line"></div>
                    <div class="sig-label">Chairman</div>
                </div>
                <div class="sig-col sig-col-2">
                    @if ($presidentSignatureDataUri)
                        <img class="sig-img" src="{{ $presidentSignatureDataUri }}" alt="President">
                    @endif
                    <div class="sig-line"></div>
                    <div class="sig-label">President</div>
                </div>
                <div class="sig-col sig-col-3">
                    <img class="qr" src="data:{{ $qrMime }};base64,{{ $qrImage }}" alt="QR">
                    <div class="qr-cap">Scan me!</div>
                </div>
            </div>
        </div>
        <div class="band-bottom band-bottom-thin">&nbsp;</div>
    </div>
</body>
</html>
