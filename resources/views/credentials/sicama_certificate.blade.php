<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>SICAMA — Membership Certificate</title>
    <style>
        @font-face {
            font-family: 'GreatVibes';
            src: url('{{ storage_path('fonts/GreatVibes-Regular.ttf') }}') format('truetype');
            font-weight: normal;
            font-style: normal;
        }
        @page { margin: 0; size: 864pt 612pt; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #111;
            width: 864pt;
            height: 612pt;
            overflow: hidden;
        }
        .page {
            position: relative;
            width: 864pt;
            height: 612pt;
            overflow: hidden;
        }
        .bg {
            position: absolute;
            left: 0;
            top: 0;
            width: 864pt;
            height: 612pt;
        }
        .name {
            position: absolute;
            left: 90pt;
            right: 90pt;
            top: 248pt;
            text-align: center;
            font-family: 'GreatVibes', DejaVu Serif, serif;
            font-size: 42pt;
            font-style: italic;
            color: #111;
            line-height: 1.1;
        }
        .body {
            position: absolute;
            left: 110pt;
            right: 110pt;
            top: 310pt;
            text-align: center;
            font-size: 11.5pt;
            line-height: 1.55;
            color: #111;
        }
        .body .role {
            font-weight: bold;
            text-transform: uppercase;
        }
        .body .org {
            font-weight: bold;
            text-transform: uppercase;
        }
        .dated {
            position: absolute;
            left: 110pt;
            right: 110pt;
            top: 388pt;
            text-align: center;
            font-size: 12pt;
            font-weight: bold;
            font-style: italic;
            color: #c4121a;
        }
        .badge-id {
            position: absolute;
            right: 48pt;
            top: 142pt;
            width: 122pt;
            text-align: center;
            font-size: 7.8pt;
            font-weight: bold;
            color: #111;
            line-height: 1.15;
        }
    </style>
</head>
<body>
    <div class="page">
        @if ($certificateBgDataUri)
            <img class="bg" src="{{ $certificateBgDataUri }}" alt="">
        @endif

        <div class="badge-id">{!! $certificateBadgeNumberHtml !!}</div>

        <div class="name">{{ $member->user->name }}</div>

        <div class="body">
            This is to certify that {{ $member->user->name }}<br>
            Has this day been admitted as <span class="role">{{ strtoupper($certificateRole) }}</span> of the association of<br>
            <span class="org">“SCHOLARS IN COMMUNICATION AND MEDIA ADVANCEMENT<br>
            INITIATIVE”</span> (SICAMA Initiative) for the year {{ $membershipPeriodLabel }}
        </div>

        <div class="dated">Dated this {{ $certificateDatedLabel }}</div>
    </div>
</body>
</html>
