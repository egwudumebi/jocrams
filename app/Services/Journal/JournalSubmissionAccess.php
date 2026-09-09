<?php

namespace App\Services\Journal;

use App\Enums\JournalSubmissionStatus;
use App\Models\JournalSubmission;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

class JournalSubmissionAccess
{
    public function __construct(private readonly JournalSubmissionRepository $repository) {}

    public function resolveSubmission(string $identifier): JournalSubmission
    {
        $uuid = $this->repository->resolveSubmissionUuid($identifier);

        if ($uuid === null) {
            throw new InvalidArgumentException('Submission not found.');
        }

        $submission = $this->repository->findByUuid($uuid);

        if ($submission === null) {
            throw new InvalidArgumentException('Submission not found.');
        }

        return $submission;
    }

    public function assertCanAssign(User $user, JournalSubmission $submission): void
    {
        if (! $user->canJournalAssign()) {
            throw new AuthorizationException('You do not have permission to assign reviewers.');
        }

        if (! in_array($submission->status, [
            JournalSubmissionStatus::Submitted,
            JournalSubmissionStatus::Resubmitted,
            JournalSubmissionStatus::UnderReview,
        ], true)) {
            throw new InvalidArgumentException('This submission cannot be assigned to a reviewer in its current state.');
        }
    }

    public function assertCanReview(User $user, JournalSubmission $submission): void
    {
        if ($user->canJournalAssign()) {
            $this->assertReviewableStatus($submission);

            return;
        }

        if (! $user->canJournalReview()) {
            throw new AuthorizationException('You do not have permission to review submissions.');
        }

        $this->assertReviewableStatus($submission);
        $this->assertAcceptedAssignment($submission->uuid, (string) $user->uuid);
    }

    public function assertCanRespondToAssignment(User $user, JournalSubmission $submission, string $assignmentId): void
    {
        if (! $user->canJournalReview()) {
            throw new AuthorizationException('You do not have permission to respond to reviewer assignments.');
        }

        $assignment = $this->repository->findAssignmentById($assignmentId);

        if ($assignment === null
            || $assignment->submission_id !== $submission->uuid
            || $assignment->reviewer_id !== $user->uuid
            || $assignment->status !== 'assigned') {
            throw new InvalidArgumentException('Reviewer assignment not found or already handled.');
        }
    }

    public function assertCanViewEditorial(User $user, JournalSubmission $submission): void
    {
        if ($user->canJournalAssign()) {
            return;
        }

        if ($submission->author_id === $user->uuid) {
            return;
        }

        if ($user->canJournalReview() && $this->repository->hasAssignmentForReviewer($submission->uuid, (string) $user->uuid)) {
            return;
        }

        if ($user->canJournalPublish() && $submission->status === JournalSubmissionStatus::Approved) {
            return;
        }

        throw new AuthorizationException('You do not have permission to view editorial details for this submission.');
    }

    public function assertCanComment(User $user, JournalSubmission $submission, string $authorRole): void
    {
        $this->assertCanViewEditorial($user, $submission);

        if ($authorRole === 'author' && $submission->author_id !== $user->uuid) {
            throw new AuthorizationException('Only the submitting author can comment as author.');
        }

        if ($authorRole === 'reviewer' && ! $user->canJournalReview()) {
            throw new AuthorizationException('Only reviewers can comment as reviewer.');
        }

        if ($authorRole === 'editor' && ! $user->canJournalAssign() && ! $user->canJournalPublish()) {
            throw new AuthorizationException('Only editorial staff can comment as editor.');
        }
    }

    public function assertCanResubmit(User $user, JournalSubmission $submission): void
    {
        if ($submission->author_id !== $user->uuid) {
            throw new AuthorizationException('Only the submitting author can resubmit revisions.');
        }

        if ($submission->status !== JournalSubmissionStatus::RevisionRequested) {
            throw new InvalidArgumentException('This submission is not awaiting a revision.');
        }
    }

    public function assertCanUploadProduction(User $user, JournalSubmission $submission): void
    {
        if (! $user->canJournalPublish() && ! $user->canJournalAssign()) {
            throw new AuthorizationException('You do not have permission to upload production documents.');
        }

        if ($submission->status !== JournalSubmissionStatus::Approved) {
            throw new InvalidArgumentException('Production documents can only be uploaded for approved submissions.');
        }
    }

    public function canDownloadManuscript(
        JournalSubmission $submission,
        ?string $requesterId,
        bool $canManageJournal,
        bool $canPublish,
        bool $isMember,
    ): bool {
        $isOwner = $requesterId !== null && $submission->author_id === $requesterId;
        $isApprovedAndPublic = $submission->status === JournalSubmissionStatus::Approved && $submission->visibility->value === 'all';
        $isApprovedAndMembersOnly = $submission->status === JournalSubmissionStatus::Approved && $submission->visibility->value === 'members_only';
        $isAssignedReviewer = $requesterId !== null
            && $this->repository->hasAcceptedAssignment($submission->uuid, $requesterId);

        return $isOwner
            || $canManageJournal
            || $canPublish
            || $isAssignedReviewer
            || $isApprovedAndPublic
            || ($isApprovedAndMembersOnly && $isMember);
    }

    private function assertReviewableStatus(JournalSubmission $submission): void
    {
        if (! in_array($submission->status, [
            JournalSubmissionStatus::UnderReview,
            JournalSubmissionStatus::Resubmitted,
        ], true)) {
            throw new InvalidArgumentException('This submission is not ready for review.');
        }
    }

    private function assertAcceptedAssignment(string $submissionUuid, string $reviewerUuid): void
    {
        if (! $this->repository->hasAcceptedAssignment($submissionUuid, $reviewerUuid)) {
            throw new AuthorizationException('You must accept the reviewer assignment before reviewing this submission.');
        }
    }
}
