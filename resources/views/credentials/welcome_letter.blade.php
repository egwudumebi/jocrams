<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $siteName }} — Welcome Letter</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #1e293b;
            background: #ffffff;
            font-size: 11.5px;
            line-height: 1.55;
        }
        .page {
            padding: 42px 48px 36px;
        }
        .header {
            width: 100%;
            margin-bottom: 22px;
            border-bottom: 2px solid #0a3d91;
            padding-bottom: 14px;
        }
        .header td { vertical-align: middle; }
        .logo {
            width: 58px;
            height: 58px;
            text-align: center;
        }
        .logo img {
            max-width: 54px;
            max-height: 54px;
        }
        .org-name {
            font-size: 15px;
            font-weight: bold;
            color: #0a3d91;
            padding-left: 12px;
        }
        .org-sub {
            font-size: 9px;
            color: #64748b;
            padding-left: 12px;
            margin-top: 2px;
        }
        .meta {
            margin: 18px 0 22px;
            font-size: 11px;
            color: #475569;
        }
        .greeting {
            margin-bottom: 14px;
            font-size: 12px;
        }
        p { margin-bottom: 11px; text-align: justify; }
        ol {
            margin: 8px 0 14px 18px;
        }
        ol li {
            margin-bottom: 7px;
            text-align: justify;
        }
        .member-box {
            margin: 14px 0 16px;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            border-radius: 4px;
        }
        .member-box strong { color: #0a3d91; }
        .closing { margin-top: 18px; }
        .sign-block { margin-top: 28px; }
        .sign-name {
            margin-top: 36px;
            font-weight: bold;
            font-size: 12px;
            color: #0f172a;
        }
        .sign-title {
            font-size: 10px;
            color: #64748b;
        }
        .sig-img {
            max-height: 48px;
            max-width: 140px;
            margin-top: 8px;
        }
        .footer {
            margin-top: 28px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            font-size: 9px;
            color: #94a3b8;
        }
    </style>
</head>
<body>
@php
    $memberName = $member->user?->name ?: 'Esteemed Member';
    $membershipNumber = $member->membership_number ?: '—';
    $letterDate = ($issuedAt ?? now())->format('F j, Y');
@endphp
<div class="page">
    <table class="header">
        <tr>
            <td class="logo" style="width: 64px;">
                @if (!empty($logoDataUri))
                    <img src="{{ $logoDataUri }}" alt="{{ $siteName }}">
                @endif
            </td>
            <td>
                <div class="org-name">Scholars in Communication and Media Advancement Initiative</div>
                <div class="org-sub">SICAMA Initiative · Official Welcome Letter</div>
            </td>
        </tr>
    </table>

    <div class="meta">{{ $letterDate }}</div>

    <div class="greeting">Dear Esteemed Member,</div>

    <div class="member-box">
        This letter welcomes <strong>{{ $memberName }}</strong>
        (Membership No. <strong>{{ $membershipNumber }}</strong>)
        to the SICAMA Initiative.
    </div>

    <p>
        On behalf of the Chairman and members of the Board of Trustees and the Executive Committee, I
        welcome you very heartily to Scholars in Communication and Media Advancement Initiative,
        SICAMA Initiative for short.
    </p>
    <p>
        Our association was formally registered with the Corporate Affairs Commission (CAC) on April 26,
        2026, and issued a certificate with registration number 9514762.
    </p>

    <p>Our organization is mandated to:</p>
    <ol>
        <li>Promote education and public awareness in the field of communication and media through the organization of seminars, workshops, conferences, community outreach programmes, and the publication of academic journals.</li>
        <li>Support students, young scholars, and researchers in communication and media related discipline through mentorship, scholarships, and academic development initiatives.</li>
        <li>Encourage responsible and ethical use of media and digital communication platforms through the organization of enlightenment programmes that promote media literacy and digital responsibility in society.</li>
        <li>Collaborate with educational institutions, civil society organizations, and relevant stakeholders in advancing research, knowledge sharing, and innovation in communication for societal development.</li>
        <li>Undertake suitable educational programmes that empower youths and communities with communication skills, digital media knowledge, and access to information.</li>
    </ol>

    <p>
        In striving to fulfill our mandate, an academic journal known as the Journal of Communication
        Research and Media Studies, with ISSN 3156-2779, has been registered. We continue to strengthen
        the journal’s online publications and a reputable editorial board.
    </p>
    <p>
        We therefore warmly welcome you to this academic and professional family of communication and
        media experts, and urge you to support us in building a strong and reputable professional body that
        will make a lasting impact in the field of communication and media.
    </p>
    <p>
        We count on your active participation through the prompt payment of annual dues, contribution of
        ideas and constructive suggestions to the leadership, submission of scholarly articles to our journal,
        and attendance at our conferences, workshops, seminars, and other programmes whenever they are
        convened.
    </p>
    <p>
        Thank you very much for choosing to become a part of the SICAMA Initiative. We look forward to
        your active participation, valuable contributions, and commitment to the growth and success of the
        organization.
    </p>

    <div class="closing">
        <p>Yours sincerely,</p>
        <p>For SICAMA Initiative</p>
    </div>

    <div class="sign-block">
        @if (!empty($presidentSignatureDataUri))
            <img class="sig-img" src="{{ $presidentSignatureDataUri }}" alt="President signature">
        @endif
        <div class="sign-name">HASHIM MOHAMMAD SULEIMAN, PhD</div>
        <div class="sign-title">President</div>
    </div>

    <div class="footer">
        Membership No. {{ $membershipNumber }}
        @if (!empty($expiresAt))
            · Valid until {{ \App\Support\Credentials\CardAsset::ordinalDayMonthYear($expiresAt) }}
        @endif
    </div>
</div>
</body>
</html>
