<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $article->title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; line-height: 1.5; }
        h1 { font-size: 18px; margin: 0 0 8px; }
        h2 { font-size: 13px; margin: 18px 0 6px; }
        .meta { color: #444; margin-bottom: 14px; }
        .label { color: #666; font-size: 10px; text-transform: uppercase; letter-spacing: .04em; }
        .box { border: 1px solid #ddd; padding: 12px; margin-top: 16px; }
        p { margin: 0 0 10px; text-align: justify; }
    </style>
</head>
<body>
    <div class="label">JOCRAMS · Journal of Communication Research and Media Studies</div>
    <h1>{{ $article->title }}</h1>
    <div class="meta">
        <div><strong>Authors:</strong> {{ $article->authorNames() }}</div>
        <div><strong>Type:</strong> {{ $article->article_type }}</div>
        <div><strong>Volume/Issue:</strong> {{ $article->volumeIssueLabel() }}</div>
        <div><strong>Pages:</strong> {{ $article->pagesLabel() }}</div>
    </div>

    <h2>Abstract</h2>
    <p>{{ $article->abstract }}</p>

    <h2>Keywords</h2>
    <p>{{ $article->keywords }}</p>

    <div class="box">
        <p><strong>Sample full-text PDF for Crossref landing-page review.</strong></p>
        <p>
            This document demonstrates that each JOCRAMS article has a unique landing page
            with a separate full-text PDF link. When JOCRAMS registers DOIs with Crossref,
            each DOI will resolve to the article landing page (not directly to this PDF).
        </p>
        <p>Permanent landing page path: {{ $article->landingPath() }}</p>
    </div>
</body>
</html>
