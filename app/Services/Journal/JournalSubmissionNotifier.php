<?php

namespace App\Services\Journal;

use App\Enums\NotificationChannel;
use App\Enums\NotificationLogStatus;
use App\Mail\JournalNotificationMail;
use App\Models\JournalSubmission;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class JournalSubmissionNotifier
{
    public function __construct(private readonly JournalSubmissionRepository $repository) {}

    public function notifySubmissionReceived(string $submissionUuid): void
    {
        $submission = $this->requireSubmission($submissionUuid);

        $this->queueToAuthor(
            $submission,
            event: 'journal.submission.received',
            subject: 'Journal submission received',
            body: $this->greeting($submission)."\n\n"
                ."We have received your manuscript \"{$submission->title}\".\n"
                ."Current status: submitted.\n\n"
                ."You can track progress in your journal submissions area.\n",
        );
    }

    public function notifyReviewerAssigned(string $submissionUuid, string $reviewerId, ?string $dueAt): void
    {
        $submission = $this->requireSubmission($submissionUuid);
        $reviewer = $this->userContact($reviewerId);

        if ($reviewer !== null) {
            $dueLine = $dueAt ? "Due date: {$dueAt}\n" : '';

            $this->queueEmail(
                recipientUserUuid: $reviewer['id'],
                recipientEmail: $reviewer['email'],
                event: 'journal.reviewer.assignment',
                subject: 'New manuscript assigned for review',
                body: "Hello {$reviewer['name']},\n\n"
                    ."You have been assigned to review \"{$submission->title}\".\n"
                    .$dueLine
                    ."\nPlease sign in and open your reviewer queue.\n",
            );
        }

        $this->queueToAuthor(
            $submission,
            event: 'journal.submission.under_review',
            subject: 'Your manuscript is under review',
            body: $this->greeting($submission)."\n\n"
                ."Your submission \"{$submission->title}\" is now under review.\n"
                ."We will notify you when there is an update.\n",
        );
    }

    public function notifyApproved(string $submissionUuid, ?string $reviewComment): void
    {
        $submission = $this->requireSubmission($submissionUuid);

        $this->queueToAuthor(
            $submission,
            event: 'journal.submission.approved',
            subject: 'Your manuscript has been approved',
            body: $this->greeting($submission)."\n\n"
                ."Congratulations. Your submission \"{$submission->title}\" has been approved.\n"
                .$this->commentBlock($reviewComment)
                ."\nSign in for next steps.\n",
        );
    }

    public function notifyRejected(string $submissionUuid, ?string $rejectionReason, ?string $reviewComment): void
    {
        $submission = $this->requireSubmission($submissionUuid);
        $reason = trim((string) ($rejectionReason ?? ''));
        $reasonBlock = $reason !== '' ? "\nReason: {$reason}\n" : '';

        $this->queueToAuthor(
            $submission,
            event: 'journal.submission.rejected',
            subject: 'Update on your manuscript submission',
            body: $this->greeting($submission)."\n\n"
                ."Your submission \"{$submission->title}\" was not accepted at this time."
                .$reasonBlock
                .$this->commentBlock($reviewComment)
                ."\nSign in to view details.\n",
        );
    }

    public function notifyRevisionRequested(string $submissionUuid, ?string $note): void
    {
        $submission = $this->requireSubmission($submissionUuid);

        $this->queueToAuthor(
            $submission,
            event: 'journal.submission.revision_requested',
            subject: 'Revision requested for your manuscript',
            body: $this->greeting($submission)."\n\n"
                ."A reviewer has requested revisions for \"{$submission->title}\".\n"
                .$this->commentBlock($note, 'Reviewer note')
                ."\nPlease sign in, update your manuscript, and resubmit.\n",
        );
    }

    public function notifyResubmitted(string $submissionUuid, int $revisionNumber): void
    {
        $submission = $this->requireSubmission($submissionUuid);

        $this->queueToAuthor(
            $submission,
            event: 'journal.submission.resubmitted',
            subject: 'Revision resubmitted successfully',
            body: $this->greeting($submission)."\n\n"
                ."We received revision {$revisionNumber} for \"{$submission->title}\".\n"
                ."Your submission will be reviewed again.\n",
        );

        $reviewerId = $submission->reviewer_id;
        if (! is_string($reviewerId) || $reviewerId === '') {
            return;
        }

        $reviewer = $this->userContact($reviewerId);
        if ($reviewer === null) {
            return;
        }

        $this->queueEmail(
            recipientUserUuid: $reviewer['id'],
            recipientEmail: $reviewer['email'],
            event: 'journal.submission.resubmitted.reviewer',
            subject: 'Author resubmitted a manuscript',
            body: "Hello {$reviewer['name']},\n\n"
                ."The author resubmitted \"{$submission->title}\" (revision {$revisionNumber}).\n"
                ."Please sign in and review the updated manuscript.\n",
        );
    }

    private function requireSubmission(string $submissionUuid): JournalSubmission
    {
        $submission = $this->repository->findByUuid($submissionUuid);
        if ($submission === null) {
            throw new \RuntimeException('Journal submission not found.');
        }

        return $submission;
    }

    private function greeting(JournalSubmission $submission): string
    {
        $name = trim($submission->author_name);

        return $name !== '' ? "Hello {$name}," : 'Hello,';
    }

    private function commentBlock(?string $comment, string $label = 'Comment'): string
    {
        $comment = trim((string) ($comment ?? ''));
        if ($comment === '') {
            return '';
        }

        return "\n{$label}: {$comment}\n";
    }

    /** @return array{id: string, email: string, name: string}|null */
    private function userContact(string $userUuid): ?array
    {
        $user = DB::table('users')->where('uuid', $userUuid)->first(['uuid', 'email', 'name']);
        if ($user === null) {
            return null;
        }

        $email = trim((string) ($user->email ?? ''));
        if ($email === '') {
            return null;
        }

        return [
            'id' => (string) $user->uuid,
            'email' => $email,
            'name' => trim((string) ($user->name ?? '')) ?: 'Reviewer',
        ];
    }

    private function queueToAuthor(
        JournalSubmission $submission,
        string $event,
        string $subject,
        string $body,
    ): void {
        $email = trim($submission->author_email);
        if ($email === '') {
            return;
        }

        $this->queueEmail(
            recipientUserUuid: $submission->author_id,
            recipientEmail: $email,
            event: $event,
            subject: $subject,
            body: $body,
        );
    }

    private function queueEmail(
        ?string $recipientUserUuid,
        string $recipientEmail,
        string $event,
        string $subject,
        string $body,
    ): void {
        $user = $recipientUserUuid
            ? User::query()->where('uuid', $recipientUserUuid)->first()
            : null;

        $log = NotificationLog::query()->create([
            'notifiable_type' => $user?->getMorphClass(),
            'notifiable_id' => $user?->getKey(),
            'channel' => NotificationChannel::Email,
            'event' => $event,
            'recipient' => $recipientEmail,
            'subject' => $subject,
            'body' => $body,
            'status' => NotificationLogStatus::Queued,
        ]);

        try {
            Mail::to($recipientEmail)->send(new JournalNotificationMail($subject, $body));
            $log->update(['status' => NotificationLogStatus::Sent, 'sent_at' => now()]);
        } catch (\Throwable $e) {
            $log->update(['status' => NotificationLogStatus::Failed, 'error' => $e->getMessage()]);
        }

        if ($user) {
            $this->queueInApp($user, $event, $subject, $body);
        }
    }

    private function queueInApp(
        User $user,
        string $event,
        string $subject,
        string $body,
    ): void {
        NotificationLog::query()->create([
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->getKey(),
            'channel' => NotificationChannel::InApp,
            'event' => $event,
            'recipient' => $user->email,
            'subject' => $subject,
            'body' => $body,
            'status' => NotificationLogStatus::Sent,
            'sent_at' => now(),
        ]);
    }
}
