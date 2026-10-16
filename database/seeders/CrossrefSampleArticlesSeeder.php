<?php

namespace Database\Seeders;

use App\Enums\JournalIssueStatus;
use App\Enums\JournalVolumeStatus;
use App\Enums\PublishedArticleStatus;
use App\Models\JournalIssue;
use App\Models\JournalVolume;
use App\Models\PublishedArticle;
use App\Services\Journal\PublishedArticleService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class CrossrefSampleArticlesSeeder extends Seeder
{
    public function run(): void
    {
        $volume = JournalVolume::query()->firstOrCreate(
            ['volume_number' => 1, 'year' => 2026],
            [
                'title' => 'Volume 1',
                'description' => 'Inaugural volume of JOCRAMS.',
                'status' => JournalVolumeStatus::Published,
                'sort_order' => 1,
                'published_at' => now(),
            ],
        );

        $issue = JournalIssue::query()->firstOrCreate(
            [
                'journal_volume_id' => $volume->id,
                'issue_number' => 1,
            ],
            [
                'title' => 'Issue 1',
                'description' => 'Demonstrational issue demonstrating JOCRAMS article landing pages for Crossref membership review.',
                'status' => JournalIssueStatus::Published,
                'is_current' => true,
                'sort_order' => 1,
                'published_at' => now(),
            ],
        );

        JournalIssue::query()
            ->where('id', '!=', $issue->id)
            ->where('is_current', true)
            ->update(['is_current' => false]);

        $issue->update([
            'status' => JournalIssueStatus::Published,
            'is_current' => true,
            'published_at' => $issue->published_at ?? now(),
        ]);

        $service = app(PublishedArticleService::class);
        $year = 2026;

        foreach ($this->samples() as $index => $sample) {
            $article = PublishedArticle::query()->updateOrCreate(
                ['slug' => $sample['slug']],
                [
                    'title' => $sample['title'],
                    'authors' => $sample['authors'],
                    'abstract' => $sample['abstract'],
                    'keywords' => $sample['keywords'],
                    'article_type' => $sample['article_type'],
                    'received_at' => $sample['received_at'],
                    'revised_at' => $sample['revised_at'],
                    'accepted_at' => $sample['accepted_at'],
                    'published_at' => $sample['published_at'],
                    'journal_issue_id' => $issue->id,
                    'volume_number' => 1,
                    'issue_number' => 1,
                    'page_start' => $sample['page_start'],
                    'page_end' => $sample['page_end'],
                    'copyright' => "© {$year} JOCRAMS / Scholars in Communication and Media Advancement Initiative (SICAMA).",
                    'license' => 'Creative Commons Attribution 4.0 International (CC BY 4.0)',
                    'doi' => null,
                    'status' => PublishedArticleStatus::Published,
                    'is_crossref_sample' => true,
                    'sort_order' => $index + 1,
                ],
            );

            $article->citation = $service->buildCitation($article);
            $this->ensureSamplePdf($article, $sample);
            $article->save();
        }
    }

    /** @param array<string, mixed> $sample */
    private function ensureSamplePdf(PublishedArticle $article, array $sample): void
    {
        if ($article->pdf_path && Storage::disk('local')->exists($article->pdf_path)) {
            return;
        }

        $html = view('articles.sample-pdf', [
            'article' => $article,
            'sample' => $sample,
        ])->render();

        $pdf = Pdf::loadHTML($html)->setPaper('a4');
        $path = 'published-articles/'.$article->uuid.'/'.$article->slug.'.pdf';
        Storage::disk('local')->put($path, $pdf->output());

        $article->pdf_path = $path;
        $article->pdf_original_filename = $article->slug.'.pdf';
    }

    /** @return list<array<string, mixed>> */
    private function samples(): array
    {
        return [
            [
                'slug' => 'digital-media-literacy-and-civic-engagement-in-nigeria',
                'title' => 'Digital Media Literacy and Civic Engagement among Nigerian University Students',
                'authors' => [
                    [
                        'name' => 'Adaobi N. Okeke',
                        'affiliation' => 'Department of Mass Communication, University of Nigeria, Nsukka, Nigeria',
                        'orcid' => '0000-0002-1111-1001',
                    ],
                    [
                        'name' => 'Chinedu E. Nwosu',
                        'affiliation' => 'Department of Communication Arts, University of Uyo, Nigeria',
                        'orcid' => null,
                    ],
                ],
                'abstract' => 'This study examines how digital media literacy shapes civic engagement among undergraduate students in Nigerian public universities. Drawing on a mixed-methods survey of 480 respondents across three institutions, the findings show that critical evaluation skills and platform fluency significantly predict participation in online public discourse and campus civic activities. The paper argues that communication curricula should embed structured digital literacy modules to strengthen democratic participation in digitally mediated public spheres.',
                'keywords' => 'digital media literacy, civic engagement, Nigerian universities, youth participation, media education',
                'article_type' => 'Research Article',
                'received_at' => '2025-11-12',
                'revised_at' => '2026-01-18',
                'accepted_at' => '2026-02-04',
                'published_at' => '2026-03-15',
                'page_start' => '1',
                'page_end' => '18',
            ],
            [
                'slug' => 'framing-public-health-communication-during-epidemics',
                'title' => 'Framing Public Health Communication during Epidemics: Lessons from Community Radio in Southern Nigeria',
                'authors' => [
                    [
                        'name' => 'Blessing T. Adeyemi',
                        'affiliation' => 'Department of Mass Communication, University of Calabar, Nigeria',
                        'orcid' => '0000-0002-2222-2002',
                    ],
                    [
                        'name' => 'Ifeanyi J. Uche',
                        'affiliation' => 'Centre for Media and Public Health Communication, Calabar, Nigeria',
                        'orcid' => '0000-0002-2222-2003',
                    ],
                ],
                'abstract' => 'Community radio remains a trusted channel for public health messaging in many Nigerian localities. This article analyses epidemic-period broadcasts from selected community stations in southern Nigeria and identifies dominant framing strategies used to communicate risk, uncertainty, and protective behaviour. Results indicate that culturally grounded narratives and local language framing improve message uptake, while fear-based appeals without actionable guidance reduce trust. Implications for crisis communication practice and media policy are discussed.',
                'keywords' => 'public health communication, framing, community radio, epidemic response, southern Nigeria',
                'article_type' => 'Research Article',
                'received_at' => '2025-12-02',
                'revised_at' => '2026-02-10',
                'accepted_at' => '2026-02-28',
                'published_at' => '2026-03-15',
                'page_start' => '19',
                'page_end' => '37',
            ],
            [
                'slug' => 'social-media-influencers-and-youth-political-participation',
                'title' => 'Social Media Influencers and Youth Political Participation in West Africa: A Comparative Study',
                'authors' => [
                    [
                        'name' => 'Fatima A. Bello',
                        'affiliation' => 'Department of Communication and Language Arts, University of Ibadan, Nigeria',
                        'orcid' => '0000-0002-3333-3003',
                    ],
                    [
                        'name' => 'Kwame Osei Mensah',
                        'affiliation' => 'School of Communication Studies, University of Ghana, Legon, Ghana',
                        'orcid' => null,
                    ],
                    [
                        'name' => 'Ngozi C. Eze',
                        'affiliation' => 'Department of Mass Communication, Nnamdi Azikiwe University, Awka, Nigeria',
                        'orcid' => '0000-0002-3333-3004',
                    ],
                ],
                'abstract' => 'This comparative study investigates the role of social media influencers in shaping youth political participation in Nigeria and Ghana. Using survey data and platform content analysis, the research explores how influencer credibility, issue framing, and parasocial relationships relate to political information seeking, discussion, and offline mobilisation among young adults. The study finds that influencers amplify awareness and conversation, yet durable participatory outcomes depend on institutional trust and civic infrastructure beyond platform engagement.',
                'keywords' => 'social media influencers, political participation, youth, West Africa, comparative communication',
                'article_type' => 'Research Article',
                'received_at' => '2026-01-08',
                'revised_at' => null,
                'accepted_at' => '2026-03-01',
                'published_at' => '2026-03-15',
                'page_start' => '38',
                'page_end' => '56',
            ],
        ];
    }
}
