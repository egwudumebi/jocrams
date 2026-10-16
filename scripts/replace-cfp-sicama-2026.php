<?php

/**
 * JOCRAMS only — replace existing call for papers with the SICAMA/JOCRAMS 2026 flyer CFP.
 *
 * Usage (on server):
 *   /opt/alt/php83/usr/bin/php scripts/replace-cfp-sicama-2026.php
 */

use App\Enums\JournalCallStatus;
use App\Models\EditorialBoardMember;
use App\Models\JournalCallForPapers;
use App\Models\JournalIssue;
use App\Models\JournalSubmission;
use App\Models\User;
use App\Services\Journal\JournalCallForPapersService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$admin = User::query()->where('email', 'admin@jocrams.test')->first()
    ?? User::query()->role('admin')->first();

if (! $admin) {
    fwrite(STDERR, "No admin user found.\n");
    exit(1);
}

$issue = JournalIssue::query()->with('volume')->orderBy('id')->first();
if (! $issue) {
    fwrite(STDERR, "No journal issue found.\n");
    exit(1);
}

DB::transaction(function () use ($admin, $issue): void {
    $calls = JournalCallForPapers::query()->with('submissions')->get();

    foreach ($calls as $call) {
        foreach ($call->submissions as $submission) {
            deleteSubmissionCascade($submission);
        }

        // Any orphaned submissions still pointing at this call
        JournalSubmission::query()
            ->where('call_for_papers_id', $call->id)
            ->get()
            ->each(fn (JournalSubmission $submission) => deleteSubmissionCascade($submission));

        $call->editorialBoardMembers()->detach();
        $call->delete();
        echo "Deleted call #{$call->id} {$call->title}\n";
    }

    // Safety: remove any remaining submissions (demo leftovers under old CFP)
    JournalSubmission::query()
        ->get()
        ->each(function (JournalSubmission $submission): void {
            deleteSubmissionCascade($submission);
            echo "Deleted leftover submission {$submission->uuid}\n";
        });

    $topics = [
        'Communication Studies',
        'Journalism and Media Studies',
        'Mass Communication',
        'Digital Media and New Media',
        'Artificial Intelligence and Communication',
        'Public Relations',
        'Advertising and Marketing Communication',
        'Strategic and Corporate Communication',
        'Political Communication',
        'Development Communication',
        'Health Communication',
        'Media Ethics, Law and Regulation',
        'Media, Culture and Society',
        'Broadcasting',
        'Film, Screen and Video',
        'Information and Communication Technologies',
        'Interdisciplinary Communication Research',
    ];

    $body = <<<'HTML'
<p><strong>JOURNAL OF COMMUNICATION RESEARCH AND MEDIA STUDIES (JOCRAMS)</strong><br>
A Publication of the Scholars in Communication and Media Advancement Initiative (SICAMA Initiative)</p>
<p><em>Advancing Communication and Media Scholarship for the Good of the Society</em></p>
<p>SICAMA is a scholarly initiative dedicated to advancing research, scholarship, publication and professional development in communication and media, connecting researchers and academia, and creating platforms for knowledge exchange and excellence.</p>

<h2>Key Dates (2026)</h2>
<ul>
<li><strong>Submission of Full Paper:</strong> October 1st – October 30th, 2026</li>
<li><strong>Notification of Acceptance:</strong> November 7th – November 21st, 2026</li>
<li><strong>Publication:</strong> December 31st, 2026</li>
</ul>

<h2>Article Processing Charge (APC)</h2>
<ul>
<li><strong>For Nigerians:</strong> ₦40,000</li>
<li><strong>For Foreigners:</strong> $40</li>
<li><strong>Payment breakdown:</strong> ₦5,000 for review | ₦35,000 if accepted</li>
</ul>

<h2>Areas of Publication</h2>
<ul>
<li>Communication Studies</li>
<li>Journalism and Media Studies</li>
<li>Mass Communication</li>
<li>Digital Media and New Media</li>
<li>Artificial Intelligence and Communication</li>
<li>Public Relations</li>
<li>Advertising and Marketing Communication</li>
<li>Strategic and Corporate Communication</li>
<li>Political Communication</li>
<li>Development Communication</li>
<li>Health Communication</li>
<li>Media Ethics, Law and Regulation</li>
<li>Media, Culture and Society</li>
<li>Broadcasting</li>
<li>Film, Screen and Video</li>
<li>Information and Communication Technologies</li>
<li>Interdisciplinary Communication Research</li>
</ul>

<h2>Author Guidelines — At a Glance</h2>
<ul>
<li><strong>Layout:</strong> Single-column</li>
<li><strong>Abstract:</strong> 150–250 words</li>
<li><strong>Spacing:</strong> Double-spaced</li>
<li><strong>Keywords:</strong> 5 words</li>
<li><strong>Author information:</strong> Full name, department, institution, city, country, phone, email, ORCID where available; identify corresponding author</li>
<li><strong>Integrity:</strong> Original work; no simultaneous submission; ethics/participant privacy where applicable; funding and competing interests disclosure where applicable; substantive AI use disclosure where relevant; generative AI tools are not authors and authors remain responsible for accuracy, originality, citations, data and final manuscript</li>
<li><strong>Submission format:</strong> Microsoft Word (.docx)</li>
<li><strong>Page format:</strong> A4</li>
<li><strong>Font:</strong> Times New Roman, 12 pt</li>
<li><strong>Margins:</strong> 2.54 cm (1 inch)</li>
<li><strong>Referencing style:</strong> APA 7th edition</li>
<li><strong>Research article length:</strong> 5,000–8,000 words</li>
</ul>

<h2>Payment Details</h2>
<ul>
<li><strong>Account Name:</strong> Scholars in Communication and Media Advancement Initiative (SICAMA Initiative)</li>
<li><strong>Account Number:</strong> 1030789503</li>
<li><strong>Bank:</strong> United Bank for Africa (UBA)</li>
</ul>
<p>Send evidence of payment to <a href="mailto:jocrams2026@gmail.com">jocrams2026@gmail.com</a>.</p>

<h2>Submission &amp; Enquiries</h2>
<ul>
<li><strong>Website:</strong> <a href="https://www.jocrams.com">www.jocrams.com</a></li>
<li><strong>Evidence of payment email:</strong> <a href="mailto:jocrams2026@gmail.com">jocrams2026@gmail.com</a></li>
<li><strong>Phone:</strong> +234 803 552 5785 · +234 803 314 3777 · +234 803 736 3683 · +234 703 067 3913</li>
</ul>
HTML;

    $boardUuids = EditorialBoardMember::query()
        ->where('is_active', true)
        ->orderBy('sort_order')
        ->pluck('uuid')
        ->all();

    /** @var JournalCallForPapersService $service */
    $service = app(JournalCallForPapersService::class);

    $call = $service->create($admin, [
        'journal_issue_uuid' => $issue->uuid,
        'title' => 'Call for Paper Submissions — JOCRAMS 2026',
        'slug' => 'call-for-paper-submissions-jocrams-2026',
        'excerpt' => 'SICAMA Initiative invites full-paper submissions to JOCRAMS. Submission window: 1–30 October 2026. APC: ₦40,000 (Nigerians) / $40 (foreigners) — ₦5,000 review + ₦35,000 on acceptance. Publication: 31 December 2026.',
        'body' => $body,
        'opens_at' => '2026-10-01 00:00:00',
        'closes_at' => '2026-10-30 23:59:59',
        'submission_fee' => 5000,
        'publication_fee' => 35000,
        'currency' => 'NGN',
        'status' => JournalCallStatus::Open->value,
        'topics' => $topics,
        'editorial_board_member_ids' => $boardUuids,
    ]);

    // Align issue labelling with Dec 2026 publication window from the flyer
    $issue->update([
        'title' => 'December 2026 Issue',
        'description' => 'JOCRAMS Vol. '.$issue->volume?->volume_number.' — Publication target 31 December 2026',
        'is_current' => true,
    ]);

    echo "Created call {$call->uuid} status={$call->status->value} board=".count($boardUuids)." fees={$call->submission_fee}+{$call->publication_fee} {$call->currency}\n";
    echo "Opens {$call->opens_at} closes {$call->closes_at}\n";
});

function deleteSubmissionCascade(JournalSubmission $submission): void
{
    $uuid = $submission->uuid;

    foreach (['journal_reviewer_assignments', 'journal_submission_timelines', 'journal_editorial_comments', 'journal_submission_revisions'] as $table) {
        if (Schema::hasTable($table)) {
            DB::table($table)->where('submission_id', $uuid)->delete();
        }
    }

    if (Schema::hasTable('payment_proofs')) {
        DB::table('payment_proofs')->where('related_uuid', $uuid)->delete();
    }

    if (Schema::hasTable('payments')) {
        DB::table('payments')->where('related_uuid', $uuid)->delete();
    }

    foreach ([$submission->document_path, $submission->production_document_path] as $path) {
        if (is_string($path) && $path !== '') {
            try {
                Storage::disk('local')->delete($path);
            } catch (Throwable) {
                // ignore missing files
            }
        }
    }

    $submission->delete();
    echo "Deleted submission {$uuid}\n";
}
