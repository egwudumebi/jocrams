<?php

use App\Models\Category;
use App\Models\NewsArticle;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\Settings\SiteBanner;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $author = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('slug', ['super-admin', 'admin']))
            ->orderBy('id')
            ->first()
            ?? User::query()->orderBy('id')->first();

        if ($author === null) {
            return;
        }

        $category = Category::query()->firstOrCreate(
            ['slug' => 'announcements', 'type' => 'news'],
            ['name' => 'Announcements', 'is_active' => true],
        );

        $slug = 'sicama-secures-crossref-doi-prefix';
        $title = 'BREAKING: SICAMA Secures Crossref DOI Prefix 10.68641';
        $excerpt = 'SICAMA has secured Crossref membership and DOI prefix 10.68641 for JOCRAMS. Next goal: Google Scholar indexing.';
        $body = <<<'HTML'
<p><strong>Another major milestone for JOCRAMS.</strong></p>
<p>Dear Esteemed SICAMA Members and Scholars,</p>
<p>We are delighted to announce that Scholars in Communication and Media Advancement Initiative (SICAMA) has officially secured Crossref membership and received its unique DOI prefix:</p>
<p><strong>10.68641</strong></p>
<p>This achievement strengthens our capacity to register Digital Object Identifiers (DOIs) for eligible articles published in the Journal of Communication Research and Media Studies (JOCRAMS).</p>
<ul>
<li>Functional and active journal website</li>
<li>ISSN secured</li>
<li>Crossref membership approved</li>
<li>DOI prefix assigned</li>
</ul>
<p><strong>Our next big goal: Google Scholar indexing.</strong></p>
<p>We are working towards meeting the requirements for inclusion in Google Scholar to expand the visibility and discoverability of research published in JOCRAMS.</p>
<p>With our active website, ISSN, Crossref membership, DOI registration capacity, and a growing collection of quality research, we are taking important steps towards this goal. While inclusion depends on Google Scholar's requirements and assessment, quality publications and consistent scholarly publishing practices are essential to our progress.</p>
<p><strong>Dear members, we need your manuscripts.</strong></p>
<p>This is the time to publish with JOCRAMS. We invite all SICAMA members, lecturers, researchers, postgraduate students, and scholars in communication and related disciplines to submit their quality manuscripts for consideration.</p>
<p>Every quality manuscript strengthens our journal. Every published article adds to our scholarly contribution and advances our collective visibility.</p>
<p>Let us work together to build JOCRAMS into a reputable platform for communication and media research in Nigeria, Africa, and beyond.</p>
<p><strong>Website:</strong> <a href="https://www.jocrams.com">www.jocrams.com</a><br>
<strong>Email:</strong> <a href="mailto:jocrams2026@gmail.com">jocrams2026@gmail.com</a></p>
<p>Our DOI is here. Google Scholar is our next target. Your manuscript is part of the journey.</p>
<p>Together, we publish. Together, we grow. Together, we advance!</p>
<p>Management<br>
Scholars in Communication and Media Advancement Initiative (SICAMA)<br>
Journal of Communication Research and Media Studies (JOCRAMS)</p>
HTML;

        $article = NewsArticle::query()->firstOrNew(['slug' => $slug]);
        $article->fill([
            'author_id' => $author->id,
            'category_id' => $category->id,
            'title' => $title,
            'excerpt' => $excerpt,
            'body' => $body,
            'status' => 'published',
            'visibility' => 'public',
            'published_at' => $article->published_at ?? now(),
        ]);
        $article->save();
        $article->refresh();

        $ticker = 'SICAMA secures Crossref DOI prefix 10.68641 for JOCRAMS · ISSN secured · Crossref membership approved · Next goal: Google Scholar indexing · Members & scholars: submit your manuscripts now';

        $settings = [
            'site_banner_enabled' => '1',
            'site_banner_message' => $ticker,
            'site_banner_link' => '/news/'.$article->uuid,
            'site_banner_link_label' => 'Read full announcement',
            'site_banner_style' => 'announcement',
        ];

        foreach ($settings as $key => $value) {
            SystemSetting::query()->updateOrCreate(
                ['group_name' => 'general', 'setting_key' => $key],
                ['setting_value' => $value, 'is_secret' => false],
            );
        }

        SiteBanner::clearCache();
    }

    public function down(): void
    {
        NewsArticle::query()->where('slug', 'sicama-secures-crossref-doi-prefix')->delete();

        SystemSetting::query()->updateOrCreate(
            ['group_name' => 'general', 'setting_key' => 'site_banner_enabled'],
            ['setting_value' => '0', 'is_secret' => false],
        );

        SiteBanner::clearCache();
    }
};
