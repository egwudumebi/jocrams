<?php

namespace App\Services\Journal;

use App\Models\JournalSubmission;
use App\Support\Auth\ProfileImageUrl;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class JournalSubmissionRepository
{
    /** @param array<int, string>|null $references */
    public function store(
        string $uuid,
        string $authorId,
        string $title,
        string $authorName,
        string $authorEmail,
        string $abstract,
        string $category,
        ?string $keywords,
        ?int $minsRead,
        ?array $references,
        string $documentPath,
        string $status,
        string $visibility,
        ?int $callForPapersId = null,
        ?int $journalIssueId = null,
        ?float $submissionFeeAmount = null,
        ?float $publicationFeeAmount = null,
    ): void {
        JournalSubmission::query()->create([
            'uuid' => $uuid,
            'author_id' => $authorId,
            'call_for_papers_id' => $callForPapersId,
            'journal_issue_id' => $journalIssueId,
            'title' => $title,
            'slug' => JournalSlugGenerator::uniqueForTitle($title, $uuid),
            'author_name' => $authorName,
            'author_email' => $authorEmail,
            'abstract' => $abstract,
            'category' => $category,
            'keywords' => $keywords,
            'mins_read' => $minsRead,
            'references' => $references,
            'document_path' => $documentPath,
            'status' => $status,
            'submission_fee_amount' => $submissionFeeAmount,
            'publication_fee_amount' => $publicationFeeAmount,
            'visibility' => $visibility,
        ]);
    }

    public function resolveSubmissionUuid(string $identifier): ?string
    {
        if (Str::isUuid($identifier)) {
            $uuid = DB::table('journal_submissions')->where('uuid', $identifier)->value('uuid');

            return $uuid !== null ? (string) $uuid : null;
        }

        $uuid = DB::table('journal_submissions')->where('slug', $identifier)->value('uuid');

        return $uuid !== null ? (string) $uuid : null;
    }

    public function findByUuid(string $uuid): ?JournalSubmission
    {
        return JournalSubmission::query()->where('uuid', $uuid)->first();
    }

    public function paginateFindAll(
        ?bool $isGuest,
        ?string $requesterId,
        bool $canManageJournal,
        bool $isMember,
        int $perPage,
    ): LengthAwarePaginator {
        $query = $this->baseListQuery()->orderByDesc('journal_submissions.created_at');

        if ($isGuest === true) {
            $query->where('journal_submissions.status', 'approved');
        }

        return $query->paginate($perPage)->through(
            fn ($model) => $this->mapListItem($model, $requesterId, $canManageJournal, $isMember),
        );
    }

    public function paginateFindByAuthorIdDetailed(string $authorId, int $perPage): LengthAwarePaginator
    {
        return JournalSubmission::query()
            ->where('author_id', $authorId)
            ->latest()
            ->paginate($perPage)
            ->through(fn (JournalSubmission $model) => $this->mapAuthorItem($model));
    }

    public function updateReview(
        string $uuid,
        string $status,
        ?string $reviewerId,
        ?string $reviewComment,
        ?string $rejectionReason,
    ): void {
        $updates = [
            'status' => $status,
            'review_comment' => $reviewComment,
            'rejection_reason' => $rejectionReason,
            'reviewed_at' => now(),
            'updated_at' => now(),
        ];

        if ($reviewerId !== null) {
            $updates['reviewer_id'] = $reviewerId;
        }

        JournalSubmission::query()->where('uuid', $uuid)->update($updates);
    }

    public function updateStatus(string $uuid, string $status, ?string $reviewComment = null): void
    {
        JournalSubmission::query()->where('uuid', $uuid)->update([
            'status' => $status,
            'review_comment' => $reviewComment,
            'updated_at' => now(),
        ]);
    }

    public function updateDocumentPath(string $uuid, string $documentPath): void
    {
        JournalSubmission::query()->where('uuid', $uuid)->update([
            'document_path' => $documentPath,
            'updated_at' => now(),
        ]);
    }

    public function updateProductionDocumentPath(string $uuid, string $documentPath): void
    {
        JournalSubmission::query()->where('uuid', $uuid)->update([
            'production_document_path' => $documentPath,
            'updated_at' => now(),
        ]);
    }

    public function updateVisibility(string $uuid, string $visibility): void
    {
        JournalSubmission::query()->where('uuid', $uuid)->update([
            'visibility' => $visibility,
            'updated_at' => now(),
        ]);
    }

    public function updateSubmissionMetadata(
        string $uuid,
        ?string $title,
        ?string $authorName,
        ?string $authorEmail,
        ?string $abstract,
        ?string $category,
        ?string $keywords,
    ): void {
        $updates = [];

        if ($title !== null) {
            $updates['title'] = $title;
            $updates['slug'] = JournalSlugGenerator::uniqueForTitle($title, $uuid);
        }
        if ($authorName !== null) {
            $updates['author_name'] = $authorName;
        }
        if ($authorEmail !== null) {
            $updates['author_email'] = $authorEmail;
        }
        if ($abstract !== null) {
            $updates['abstract'] = $abstract;
        }
        if ($category !== null) {
            $updates['category'] = $category;
        }
        if ($keywords !== null) {
            $updates['keywords'] = $keywords;
        }

        if ($updates === []) {
            return;
        }

        $updates['updated_at'] = now();
        JournalSubmission::query()->where('uuid', $uuid)->update($updates);
    }

    public function assignReviewer(
        string $assignmentId,
        string $submissionUuid,
        string $assignedBy,
        string $reviewerId,
        string $priority,
        ?string $dueAt,
    ): void {
        DB::table('journal_reviewer_assignments')
            ->where('submission_id', $submissionUuid)
            ->whereIn('status', ['assigned', 'accepted'])
            ->update([
                'status' => 'replaced',
                'updated_at' => now(),
            ]);

        DB::table('journal_reviewer_assignments')->insert([
            'id' => $assignmentId,
            'submission_id' => $submissionUuid,
            'assigned_by' => $assignedBy,
            'reviewer_id' => $reviewerId,
            'priority' => $priority,
            'due_at' => $dueAt,
            'status' => 'assigned',
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function findAssignmentById(string $assignmentId): ?object
    {
        return DB::table('journal_reviewer_assignments')->where('id', $assignmentId)->first();
    }

    public function hasAssignmentForReviewer(string $submissionUuid, string $reviewerId): bool
    {
        return DB::table('journal_reviewer_assignments')
            ->where('submission_id', $submissionUuid)
            ->where('reviewer_id', $reviewerId)
            ->whereIn('status', ['assigned', 'accepted'])
            ->exists();
    }

    public function hasAcceptedAssignment(string $submissionUuid, string $reviewerId): bool
    {
        return DB::table('journal_reviewer_assignments')
            ->where('submission_id', $submissionUuid)
            ->where('reviewer_id', $reviewerId)
            ->where('status', 'accepted')
            ->exists();
    }

    public function updateAssignmentStatus(string $assignmentId, string $status): void
    {
        DB::table('journal_reviewer_assignments')
            ->where('id', $assignmentId)
            ->update([
                'status' => $status,
                'completed_at' => in_array($status, ['declined', 'completed', 'replaced'], true) ? now() : null,
                'updated_at' => now(),
            ]);
    }

    /** @param array<string, mixed> $metadata */
    public function addTimelineEvent(
        string $timelineId,
        string $submissionUuid,
        ?string $actorId,
        string $event,
        array $metadata,
        string $occurredAt,
    ): void {
        DB::table('journal_submission_timelines')->insert([
            'id' => $timelineId,
            'submission_id' => $submissionUuid,
            'actor_id' => $actorId,
            'event' => $event,
            'metadata' => json_encode($metadata),
            'occurred_at' => $occurredAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function addEditorialComment(
        string $commentId,
        string $submissionUuid,
        string $authorId,
        string $authorRole,
        string $comment,
        ?string $parentId,
    ): void {
        DB::table('journal_editorial_comments')->insert([
            'id' => $commentId,
            'submission_id' => $submissionUuid,
            'author_id' => $authorId,
            'author_role' => $authorRole,
            'parent_id' => $parentId,
            'comment' => $comment,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function addRevision(
        string $revisionId,
        string $submissionUuid,
        string $submittedBy,
        int $revisionNumber,
        string $documentPath,
        ?string $note,
        string $status,
    ): void {
        DB::table('journal_submission_revisions')->insert([
            'id' => $revisionId,
            'submission_id' => $submissionUuid,
            'submitted_by' => $submittedBy,
            'revision_number' => $revisionNumber,
            'document_path' => $documentPath,
            'note' => $note,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function nextRevisionNumber(string $submissionUuid): int
    {
        $max = DB::table('journal_submission_revisions')
            ->where('submission_id', $submissionUuid)
            ->max('revision_number');

        return ((int) $max) + 1;
    }

    /** @return list<array<string, mixed>> */
    public function timeline(string $submissionUuid): array
    {
        return DB::table('journal_submission_timelines')
            ->where('submission_id', $submissionUuid)
            ->orderBy('occurred_at')
            ->get()
            ->map(fn ($item) => (array) $item)
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public function comments(string $submissionUuid): array
    {
        return DB::table('journal_editorial_comments')
            ->where('submission_id', $submissionUuid)
            ->orderBy('created_at')
            ->get()
            ->map(fn ($item) => (array) $item)
            ->all();
    }

    public function paginateQueue(?string $reviewerId, int $perPage): LengthAwarePaginator
    {
        $query = DB::table('journal_submissions')
            ->join('journal_reviewer_assignments', function ($join): void {
                $join->on('journal_submissions.uuid', '=', 'journal_reviewer_assignments.submission_id')
                    ->whereIn('journal_reviewer_assignments.status', ['assigned', 'accepted']);
            })
            ->leftJoin('users as authors', 'journal_submissions.author_id', '=', 'authors.uuid')
            ->leftJoin('user_profiles as author_profiles', 'authors.uuid', '=', 'author_profiles.user_id')
            ->select(
                'journal_submissions.uuid',
                'journal_submissions.slug',
                'journal_submissions.title',
                'journal_submissions.author_name',
                'journal_submissions.author_email',
                'journal_submissions.abstract',
                'journal_submissions.category',
                'journal_submissions.keywords',
                'journal_submissions.mins_read',
                'journal_submissions.references',
                'journal_submissions.author_id',
                'authors.name as author_user_name',
                'authors.email as author_user_email',
                'author_profiles.profile_image_path as author_profile_image_path',
                'journal_submissions.status',
                'journal_submissions.visibility',
                'journal_reviewer_assignments.id as assignment_id',
                'journal_reviewer_assignments.reviewer_id',
                'journal_reviewer_assignments.priority',
                'journal_reviewer_assignments.due_at',
                'journal_reviewer_assignments.status as assignment_status',
            )
            ->orderByDesc('journal_submissions.created_at');

        if ($reviewerId) {
            $query->where('journal_reviewer_assignments.reviewer_id', $reviewerId);
        }

        return $query->paginate($perPage)->through(fn ($item) => $this->mapQueueItem($item));
    }

    /** @return list<array<string, mixed>> */
    public function listReviewers(): array
    {
        return DB::table('users')
            ->join('user_role', 'users.id', '=', 'user_role.user_id')
            ->join('roles', 'user_role.role_id', '=', 'roles.id')
            ->where('roles.slug', 'reviewer')
            ->select('users.uuid', 'users.name', 'users.email')
            ->orderBy('users.name')
            ->get()
            ->map(fn ($user) => [
                'id' => (string) $user->uuid,
                'name' => (string) $user->name,
                'email' => (string) $user->email,
            ])
            ->all();
    }

    private function baseListQuery()
    {
        return DB::table('journal_submissions')
            ->leftJoin('users as reviewers', 'journal_submissions.reviewer_id', '=', 'reviewers.uuid')
            ->leftJoin('users as authors', 'journal_submissions.author_id', '=', 'authors.uuid')
            ->leftJoin('user_profiles as author_profiles', 'authors.uuid', '=', 'author_profiles.user_id')
            ->select(
                'journal_submissions.*',
                'reviewers.name as reviewer_name',
                'authors.name as author_user_name',
                'authors.email as author_user_email',
                'author_profiles.profile_image_path as author_profile_image_path',
            );
    }

    /** @return array<string, mixed> */
    private function mapListItem(object $model, ?string $requesterId, bool $canManageJournal, bool $isMember): array
    {
        $visibility = (string) ($model->visibility ?? 'all');
        $canAccess = $this->canAccessDocument(
            (string) $model->author_id,
            (string) $model->status,
            $visibility,
            $requesterId,
            $canManageJournal,
            $isMember,
            (string) $model->uuid,
        );

        return [
            'id' => (string) $model->uuid,
            'slug' => $model->slug ? (string) $model->slug : null,
            'author_id' => (string) $model->author_id,
            'author_user_name' => $model->author_user_name ? (string) $model->author_user_name : null,
            'author_user_email' => $model->author_user_email ? (string) $model->author_user_email : null,
            'author_profile_image' => ProfileImageUrl::forUser(
                (string) $model->author_id,
                isset($model->author_profile_image_path) ? (string) $model->author_profile_image_path : null,
            ),
            'title' => (string) $model->title,
            'author_name' => (string) $model->author_name,
            'author_email' => (string) $model->author_email,
            'abstract' => (string) $model->abstract,
            'category' => (string) $model->category,
            'keywords' => $model->keywords ? (string) $model->keywords : null,
            'mins_read' => $model->mins_read ? (int) $model->mins_read : null,
            'references' => $model->references ? (array) json_decode((string) $model->references, true) : null,
            'can_access' => $canAccess,
            'document_url' => $this->documentUrlFor($model, $canAccess),
            'status' => (string) $model->status,
            'visibility' => $visibility,
            'reviewer_id' => $model->reviewer_id ? (string) $model->reviewer_id : null,
            'reviewer_name' => $model->reviewer_name ? (string) $model->reviewer_name : null,
            'review_comment' => $model->review_comment ? (string) $model->review_comment : null,
            'rejection_reason' => $model->rejection_reason ? (string) $model->rejection_reason : null,
            'date_submitted' => $model->created_at,
            'date_reviewed' => $model->reviewed_at,
        ];
    }

    /** @return array<string, mixed> */
    private function mapAuthorItem(JournalSubmission $model): array
    {
        return [
            'id' => (string) $model->uuid,
            'slug' => $model->slug,
            'author_id' => (string) $model->author_id,
            'title' => (string) $model->title,
            'author_name' => (string) $model->author_name,
            'author_email' => (string) $model->author_email,
            'abstract' => (string) $model->abstract,
            'category' => (string) $model->category,
            'keywords' => $model->keywords,
            'mins_read' => $model->mins_read,
            'references' => $model->references,
            'document_url' => JournalSubmissionUrls::document((string) $model->uuid, $model->slug),
            'status' => $model->status->value,
            'visibility' => $model->visibility->value,
            'reviewer_id' => $model->reviewer_id,
            'review_comment' => $model->review_comment,
            'rejection_reason' => $model->rejection_reason,
            'date_submitted' => $model->created_at,
            'date_reviewed' => $model->reviewed_at,
        ];
    }

    /** @return array<string, mixed> */
    private function mapQueueItem(object $item): array
    {
        return [
            'id' => (string) $item->uuid,
            'slug' => $item->slug ? (string) $item->slug : null,
            'author_id' => (string) $item->author_id,
            'author_user_name' => $item->author_user_name ? (string) $item->author_user_name : null,
            'author_user_email' => $item->author_user_email ? (string) $item->author_user_email : null,
            'author_profile_image' => ProfileImageUrl::forUser(
                (string) $item->author_id,
                isset($item->author_profile_image_path) ? (string) $item->author_profile_image_path : null,
            ),
            'title' => (string) $item->title,
            'author_name' => (string) $item->author_name,
            'author_email' => $item->author_email ? (string) $item->author_email : null,
            'abstract' => $item->abstract ? (string) $item->abstract : null,
            'category' => $item->category ? (string) $item->category : null,
            'keywords' => $item->keywords ? (string) $item->keywords : null,
            'mins_read' => $item->mins_read !== null ? (int) $item->mins_read : null,
            'references' => $item->references ? (array) json_decode((string) $item->references, true) : null,
            'document_url' => JournalSubmissionUrls::document((string) $item->uuid, $item->slug ? (string) $item->slug : null),
            'status' => (string) $item->status,
            'visibility' => $item->visibility ? (string) $item->visibility : 'all',
            'assignment_id' => isset($item->assignment_id) ? (string) $item->assignment_id : null,
            'assignment_status' => isset($item->assignment_status) ? (string) $item->assignment_status : null,
            'reviewer_id' => $item->reviewer_id ? (string) $item->reviewer_id : null,
            'priority' => $item->priority ? (string) $item->priority : null,
            'due_at' => $item->due_at,
        ];
    }

    private function documentUrlFor(object $model, bool $canAccess = true): ?string
    {
        if (! $canAccess) {
            return null;
        }

        $slug = isset($model->slug) && $model->slug ? (string) $model->slug : null;

        return JournalSubmissionUrls::document((string) $model->uuid, $slug);
    }

    private function canAccessDocument(
        string $authorId,
        string $status,
        string $visibility,
        ?string $requesterId,
        bool $canManageJournal,
        bool $isMember,
        ?string $submissionUuid = null,
    ): bool {
        $isOwner = $requesterId !== null && $authorId === $requesterId;
        $isApprovedAndPublic = $status === 'approved' && $visibility === 'all';
        $isApprovedAndMembersOnly = $status === 'approved' && $visibility === 'members_only';
        $isAssignedReviewer = $requesterId !== null
            && $submissionUuid !== null
            && $this->hasAcceptedAssignment($submissionUuid, $requesterId);

        return $isOwner
            || $canManageJournal
            || $isAssignedReviewer
            || $isApprovedAndPublic
            || ($isApprovedAndMembersOnly && $isMember);
    }
}
