<script type="application/json" id="app-seo-data">@json([
    'branding' => $seoBranding,
    'defaults' => [
        'title' => $siteName.' — '.$siteTagline,
        'description' => $siteDescription,
        'image' => $siteLogo,
        'url' => $canonicalUrl,
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS)</script>
