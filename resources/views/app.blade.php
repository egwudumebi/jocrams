@php
    use App\Support\Settings\SiteBranding;

    try {
        $siteName = SiteBranding::siteName();
        $siteTagline = SiteBranding::siteTagline() ?: SiteBranding::journalFullName();
        $parentOrgName = SiteBranding::parentOrgName();
        $siteLogo = SiteBranding::siteLogoUrl() ?: SiteBranding::defaultLogoUrl();
        $seoBranding = SiteBranding::publicPayload();
    } catch (\Throwable) {
        $siteName = (string) config('app.name', 'JOCRAMS');
        $siteTagline = (string) config('sicama.journal.full_name', 'Journal of Communication Research and Media Studies');
        $parentOrgName = (string) config('sicama.parent_org.short_name', 'SICAMA');
        $siteLogo = url('/images/sicama-logo.png');
        $seoBranding = [
            'site_name' => $siteName,
            'site_tagline' => $siteTagline,
            'site_logo_url' => $siteLogo,
            'journal_full_name' => $siteTagline,
            'parent_org' => [
                'short_name' => $parentOrgName,
                'full_name' => (string) config('sicama.parent_org.full_name', ''),
                'motto' => (string) config('sicama.parent_org.motto', ''),
                'logo_url' => $siteLogo,
            ],
        ];
    }

    $siteDescription = trim($siteTagline.' Published by '.$parentOrgName.'. Explore membership, events, research publications, and manuscript submissions.');
    $canonicalUrl = url()->current();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $siteName }} — {{ $siteTagline }}</title>
    <meta name="description" content="{{ $siteDescription }}">
    <meta name="keywords" content="{{ $siteName }}, {{ $parentOrgName }}, communication research, media studies, academic journal, Nigeria">
    <meta name="author" content="{{ $siteName }}">
    <meta name="application-name" content="{{ $siteName }}">
    <meta name="robots" content="index,follow">
    <meta name="theme-color" content="#0f2744">

    <link rel="canonical" href="{{ $canonicalUrl }}">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="{{ $siteName }} — {{ $siteTagline }}">
    <meta property="og:description" content="{{ $siteDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:locale" content="en_NG">
    <meta property="og:image" content="{{ $siteLogo }}">
    <meta property="og:image:alt" content="{{ $siteName }} logo">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $siteName }} — {{ $siteTagline }}">
    <meta name="twitter:description" content="{{ $siteDescription }}">
    <meta name="twitter:image" content="{{ $siteLogo }}">
    <meta name="twitter:image:alt" content="{{ $siteName }} logo">

    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <script>
        window.__APP_SEO__ = {
            branding: @json($seoBranding),
            defaults: {
                title: @json($siteName . ' — ' . $siteTagline),
                description: @json($siteDescription),
                image: @json($siteLogo),
                url: @json($canonicalUrl),
            },
        };
    </script>
</head>
<body class="font-sans antialiased bg-white text-text-primary">
    <div id="app"></div>
</body>
</html>
