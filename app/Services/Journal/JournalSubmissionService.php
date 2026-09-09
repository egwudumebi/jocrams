<?php

namespace App\Services\Journal;

use App\Models\JournalCallForPapers;
use App\Models\JournalSubmission;
use App\Models\User;
use App\Services\Admin\ActivityLogService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use InvalidArgumentException;

class JournalSubmissionService
{
    public function __construct(
        private readonly JournalSubmissionRepository $repository,
        private readonly JournalDocumentStorage $documentStorage,
        private readonly JournalSubmissionNotifier $notifier,
        private readonly ActivityLogService $activityLog,
        private readonly JournalSubmissionAccess $access,
    ) {}

    /** @param array<int, string>|null $references */
    public function submit(
        User $author,
        string $title,
        string $authorName,
        string $authorEmail,
        string $abstract,
        string $category,
        ?string $keywords,
        ?int $minsRead,
        ?array $references,
        string $documentOriginalName,
        string $documentContent,
        ?JournalCallForPapers $callForPapers = null,
    ): array {
        $submissionUuid = (string) Str::uuid();
        $documentPath = 'journal-submissions/'.$submissionUuid.'/'.trim($documentOriginalName);

        $submissionFee = $callForPapers ? (float) $callForPapers->submission_fee : 0;
        $publicationFee = $callForPapers ? (float) $callForPapers->publication_fee : 0;
        $status = $submissionFee > 0 ? 'payment_pending' : 'submitted';

        $this->documentStorage->store($documentPath, $documentContent);

        $this->repository->store(
            uuid: $submissionUuid,
            authorId: (string) $author->uuid,
            title: $title,
            authorName: $authorName,
            authorEmail: $authorEmail,
            abstract: $abstract,
            category: $category,
            keywords: $keywords,
            minsRead: $minsRead,
            references: $references,
            documentPath: $documentPath,
            status: $status,
            visibility: 'all',
            callForPapersId: $callForPapers?->id,
            journalIssueId: $callForPapers?->journal_issue_id,
            submissionFeeAmount: $submissionFee > 0 ? $submissionFee : null,
            publicationFeeAmount: $publicationFee > 0 ? $publicationFee : null,
        );

        $this->repository->addRevision(
            revisionId: (string) Str::uuid(),
            submissionUuid: $submissionUuid,
            submittedBy: (string) $author->uuid,
            revisionNumber: 1,
            documentPath: $documentPath,
            note: 'Initial submission',
            status: $status,
        );

        $this->repository->addTimelineEvent(
            timelineId: (string) Str::uuid(),
            submissionUuid: $submissionUuid,
            actorId: (string) $author->uuid,
            event: 'submission.created',
            metadata: [
                'status' => $status,
                'call_for_papers_uuid' => $callForPapers?->uuid,
            ],
            occurredAt: now()->toDateTimeString(),
        );

        $this->activityLog->log($author, 'journal.submitted', properties: ['submission_id' => $submissionUuid]);

        if ($status === 'submitted') {
            $this->notifier->notifySubmissionReceived($submissionUuid);
        }

        return [
            'submission_id' => $submissionUuid,
            'status' => $status,
            'requires_payment' => $submissionFee > 0,
            'submission_fee' => $submissionFee,
            'publication_fee' => $publicationFee,
            'currency' => $callForPapers?->currency ?? config('payments.currency', 'NGN'),
        ];
    }

    public function finalizePaidSubmission(JournalSubmission $submission): void
    {
        if ($submission->status !== 'payment_pending') {
            return;
        }

        $submission->update(['status' => 'submitted']);

        $this->repository->addTimelineEvent(
            timelineId: (string) Str::uuid(),
            submissionUuid: $submission->uuid,
            actorId: (string) $submission->author_id,
            event: 'submission.payment_received',
            metadata: ['status' => 'submitted'],
            occurredAt: now()->toDateTimeString(),
        );

        $this->notifier->notifySubmissionReceived($submission->uuid);
    }

    public function review(
        User $reviewer,
        string $submissionIdentifier,
        string $decision,
        ?string $reviewComment,
        ?string $rejectionReason,
    ): void {
        $submission = $this->access->resolveSubmission($submissionIdentifier);
        $this->access->assertCanReview($reviewer, $submission);

        $status = match ($decision) {
            'assign' => 'under_review',
            'accept' => 'approved',
            'reject' => 'rejected',
            default => throw new InvalidArgumentException('Invalid decision.'),
        };

        if ($status === 'rejected' && empty($rejectionReason)) {
            throw new InvalidArgumentException('Rejection reason is required.');
        }

        $this->repository->updateReview(
            uuid: $submission->uuid,
            status: $status,
            reviewerId: (string) $reviewer->uuid,
            reviewComment: $reviewComment,
            rejectionReason: $rejectionReason,
        );

        $this->repository->addTimelineEvent(
            timelineId: (string) Str::uuid(),
            submissionUuid: $submission->uuid,
            actorId: (string) $reviewer->uuid,
            event: 'submission.'.$status,
            metadata: [
                'decision' => $decision,
                'review_comment' => $reviewComment,
            ],
            occurredAt: now()->toDateTimeString(),
        );

        $this->activityLog->log($reviewer, 'journal.reviewed', properties: [
            'submission_id' => $submission->uuid,
            'decision' => $decision,
        ]);

        match ($decision) {
            'accept' => $this->notifier->notifyApproved($submission->uuid, $reviewComment),
            'reject' => $this->notifier->notifyRejected($submission->uuid, $rejectionReason, $reviewComment),
            default => null,
        };
    }

    public function assignReviewer(
        User $assignedBy,
        string $submissionIdentifier,
        string $reviewerUuid,
        string $priority,
        ?string $dueAt,
    ): void {
        $submission = $this->access->resolveSubmission($submissionIdentifier);
        $this->access->assertCanAssign($assignedBy, $submission);

        $reviewer = User::query()->where('uuid', $reviewerUuid)->first();

        if ($reviewer === null || ! $reviewer->canJournalReview()) {
            throw new InvalidArgumentException('Selected reviewer is not eligible.');
        }

        $assignmentId = (string) Str::uuid();

        $this->repository->assignReviewer(
            assignmentId: $assignmentId,
            submissionUuid: $submission->uuid,
            assignedBy: (string) $assignedBy->uuid,
            reviewerId: $reviewerUuid,
            priority: $priority,
            dueAt: $dueAt,
        );

        $this->repository->updateReview(
            uuid: $submission->uuid,
            status: 'under_review',
            reviewerId: $reviewerUuid,
            reviewComment: null,
            rejectionReason: null,
        );

        $this->repository->addTimelineEvent(
            timelineId: (string) Str::uuid(),
            submissionUuid: $submission->uuid,
            actorId: (string) $assignedBy->uuid,
            event: 'submission.reviewer_assigned',
            metadata: [
                'reviewer_id' => $reviewerUuid,
                'priority' => $priority,
                'due_at' => $dueAt,
                'assignment_id' => $assignmentId,
            ],
            occurredAt: now()->toDateTimeString(),
        );

        $this->activityLog->log($assignedBy, 'journal.reviewer.assigned', properties: [
            'submission_id' => $submission->uuid,
            'reviewer_id' => $reviewerUuid,
        ]);

        $this->notifier->notifyReviewerAssigned($submission->uuid, $reviewerUuid, $dueAt);
    }

    public function respondToAssignment(
        User $reviewer,
        string $submissionIdentifier,
        string $assignmentId,
        string $decision,
    ): void {
        $submission = $this->access->resolveSubmission($submissionIdentifier);
        $this->access->assertCanRespondToAssignment($reviewer, $submission, $assignmentId);

        $status = match ($decision) {
            'accept' => 'accepted',
            'decline' => 'declined',
            default => throw new InvalidArgumentException('Invalid assignment response.'),
        };

        $this->repository->updateAssignmentStatus($assignmentId, $status);

        $this->repository->addTimelineEvent(
            timelineId: (string) Str::uuid(),
            submissionUuid: $submission->uuid,
            actorId: (string) $reviewer->uuid,
            event: 'submission.reviewer_assignment_'.$status,
            metadata: [
                'assignment_id' => $assignmentId,
                'decision' => $decision,
            ],
            occurredAt: now()->toDateTimeString(),
        );

        $this->activityLog->log($reviewer, 'journal.reviewer.assignment_'.$status, properties: [
            'submission_id' => $submission->uuid,
            'assignment_id' => $assignmentId,
        ]);
    }

    public function requestRevision(User $reviewer, string $submissionIdentifier, ?string $note): void
    {
        $submission = $this->access->resolveSubmission($submissionIdentifier);
        $this->access->assertCanReview($reviewer, $submission);

        $this->repository->updateReview(
            uuid: $submission->uuid,
            status: 'revision_requested',
            reviewerId: (string) $reviewer->uuid,
            reviewComment: $note,
            rejectionReason: null,
        );

        $this->repository->addTimelineEvent(
            timelineId: (string) Str::uuid(),
            submissionUuid: $submission->uuid,
            actorId: (string) $reviewer->uuid,
            event: 'submission.revision_requested',
            metadata: ['note' => $note],
            occurredAt: now()->toDateTimeString(),
        );

        $this->activityLog->log($reviewer, 'journal.revision.requested', properties: [
            'submission_id' => $submission->uuid,
        ]);

        $this->notifier->notifyRevisionRequested($submission->uuid, $note);
    }

    public function resubmit(
        User $author,
        string $submissionIdentifier,
        ?string $title,
        ?string $authorName,
        ?string $authorEmail,
        ?string $abstract,
        ?string $category,
        ?string $keywords,
        ?string $note,
        string $documentOriginalName,
        string $documentContent,
    ): void {
        $submission = $this->access->resolveSubmission($submissionIdentifier);
        $this->access->assertCanResubmit($author, $submission);

        $this->repository->updateSubmissionMetadata(
            uuid: $submission->uuid,
            title: $title,
            authorName: $authorName,
            authorEmail: $authorEmail,
            abstract: $abstract,
            category: $category,
            keywords: $keywords,
        );

        $revisionNumber = $this->repository->nextRevisionNumber($submission->uuid);
        $documentPath = 'journal-submissions/'.$submission->uuid.'/revision-'.$revisionNumber.'-'.$documentOriginalName;

        $this->documentStorage->store($documentPath, $documentContent);
        $this->repository->updateDocumentPath($submission->uuid, $documentPath);

        $this->repository->addRevision(
            revisionId: (string) Str::uuid(),
            submissionUuid: $submission->uuid,
            submittedBy: (string) $author->uuid,
            revisionNumber: $revisionNumber,
            documentPath: $documentPath,
            note: $note,
            status: 'resubmitted',
        );

        $this->repository->updateStatus($submission->uuid, 'resubmitted', $note);

        $this->repository->addTimelineEvent(
            timelineId: (string) Str::uuid(),
            submissionUuid: $submission->uuid,
            actorId: (string) $author->uuid,
            event: 'submission.resubmitted',
            metadata: ['revision_number' => $revisionNumber],
            occurredAt: now()->toDateTimeString(),
        );

        $this->activityLog->log($author, 'journal.resubmitted', properties: [
            'submission_id' => $submission->uuid,
            'revision_number' => $revisionNumber,
        ]);

        $this->notifier->notifyResubmitted($submission->uuid, $revisionNumber);
    }

    public function uploadProductionDocument(
        User $editor,
        string $submissionIdentifier,
        string $documentOriginalName,
        string $documentContent,
    ): void {
        $submission = $this->access->resolveSubmission($submissionIdentifier);
        $this->access->assertCanUploadProduction($editor, $submission);

        $documentPath = 'journal-submissions/'.$submission->uuid.'/production-'.trim($documentOriginalName);

        $this->documentStorage->store($documentPath, $documentContent);
        $this->repository->updateProductionDocumentPath($submission->uuid, $documentPath);

        $this->repository->addTimelineEvent(
            timelineId: (string) Str::uuid(),
            submissionUuid: $submission->uuid,
            actorId: (string) $editor->uuid,
            event: 'submission.production_document_uploaded',
            metadata: ['document_path' => $documentPath],
            occurredAt: now()->toDateTimeString(),
        );

        $this->activityLog->log($editor, 'journal.production.uploaded', properties: [
            'submission_id' => $submission->uuid,
        ]);
    }

    public function addComment(User $author, string $submissionIdentifier, string $authorRole, string $comment, ?string $parentId): void
    {
        $submission = $this->access->resolveSubmission($submissionIdentifier);
        $this->access->assertCanComment($author, $submission, $authorRole);

        $this->repository->addEditorialComment(
            commentId: (string) Str::uuid(),
            submissionUuid: $submission->uuid,
            authorId: (string) $author->uuid,
            authorRole: $authorRole,
            comment: $comment,
            parentId: $parentId,
        );
    }

    public function commentsFor(User $user, string $submissionIdentifier): array
    {
        $submission = $this->access->resolveSubmission($submissionIdentifier);
        $this->access->assertCanViewEditorial($user, $submission);

        return $this->repository->comments($submission->uuid);
    }

    public function setVisibility(string $submissionIdentifier, string $visibility): void
    {
        $submissionUuid = $this->resolveSubmissionUuid($submissionIdentifier);
        $this->repository->updateVisibility($submissionUuid, $visibility);
    }

    private function resolveSubmissionUuid(string $identifier): string
    {
        $uuid = $this->repository->resolveSubmissionUuid($identifier);
        if ($uuid === null) {
            throw new InvalidArgumentException('Submission not found.');
        }

        return $uuid;
    }

    /** @return array{found: bool, forbidden?: bool, file?: string, path?: string, variant?: string} */
    public function downloadDocument(
        string $submissionIdentifier,
        ?string $requesterId,
        bool $canManageJournal,
        bool $canPublish,
        bool $isMember,
        string $variant = 'manuscript',
    ): array {
        $submissionUuid = $this->repository->resolveSubmissionUuid($submissionIdentifier);
        if ($submissionUuid === null) {
            return ['found' => false];
        }

        $submission = $this->repository->findByUuid($submissionUuid);
        if ($submission === null) {
            return ['found' => false];
        }

        if ($variant === 'production') {
            if (! $canManageJournal && ! $canPublish) {
                return ['found' => true, 'forbidden' => true];
            }

            if ($submission->production_document_path === null) {
                return ['found' => true, 'forbidden' => true];
            }

            return [
                'found' => true,
                'file' => $this->documentStorage->read($submission->production_document_path),
                'path' => $submission->production_document_path,
                'variant' => 'production',
            ];
        }

        if (! $this->access->canDownloadManuscript($submission, $requesterId, $canManageJournal, $canPublish, $isMember)) {
            return ['found' => true, 'forbidden' => true];
        }

        $path = $submission->status->value === 'approved' && $submission->production_document_path
            ? $submission->production_document_path
            : $submission->document_path;

        return [
            'found' => true,
            'file' => $this->documentStorage->read($path),
            'path' => $path,
            'variant' => $path === $submission->production_document_path ? 'production' : 'manuscript',
        ];
    }
}
