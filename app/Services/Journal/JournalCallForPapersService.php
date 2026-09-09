<?php

namespace App\Services\Journal;

use App\Enums\JournalCallStatus;
use App\Models\EditorialBoardMember;
use App\Models\JournalCallForPapers;
use App\Models\User;
use App\Support\Html\HtmlSanitizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class JournalCallForPapersService
{
    public function __construct(private readonly JournalIssueService $issueService) {}

    /** @param array<string, mixed> $data */
    public function create(User $admin, array $data): JournalCallForPapers
    {
        $issue = $this->issueService->resolveByUuid($data['journal_issue_uuid']);

        $call = JournalCallForPapers::query()->create([
            'created_by' => $admin->id,
            'journal_issue_id' => $issue->id,
            'title' => $data['title'],
            'slug' => $this->uniqueSlug($data['slug'] ?? $data['title']),
            'excerpt' => $data['excerpt'] ?? null,
            'body' => HtmlSanitizer::clean($data['body'] ?? null),
            'opens_at' => $data['opens_at'] ?? null,
            'closes_at' => $data['closes_at'] ?? null,
            'submission_fee' => $data['submission_fee'] ?? 0,
            'publication_fee' => $data['publication_fee'] ?? 0,
            'currency' => $data['currency'] ?? 'NGN',
            'status' => JournalCallStatus::from($data['status'] ?? JournalCallStatus::Draft->value),
            'topics' => $data['topics'] ?? null,
        ]);

        $this->syncBoardMembers($call, $data['editorial_board_member_ids'] ?? []);

        return $call->fresh(['editorialBoardMembers', 'issue.volume']);
    }

    /** @param array<string, mixed> $data */
    public function update(JournalCallForPapers $call, array $data): JournalCallForPapers
    {
        $issueId = $call->journal_issue_id;

        if (isset($data['journal_issue_uuid'])) {
            $issueId = $this->issueService->resolveByUuid($data['journal_issue_uuid'])->id;
        }

        $call->update([
            'journal_issue_id' => $issueId,
            'title' => $data['title'] ?? $call->title,
            'slug' => isset($data['slug']) ? $this->uniqueSlug($data['slug'], $call->id) : $call->slug,
            'excerpt' => $data['excerpt'] ?? $call->excerpt,
            'body' => array_key_exists('body', $data) ? HtmlSanitizer::clean($data['body']) : $call->body,
            'opens_at' => $data['opens_at'] ?? $call->opens_at,
            'closes_at' => $data['closes_at'] ?? $call->closes_at,
            'submission_fee' => $data['submission_fee'] ?? $call->submission_fee,
            'publication_fee' => $data['publication_fee'] ?? $call->publication_fee,
            'currency' => $data['currency'] ?? $call->currency,
            'status' => isset($data['status']) ? JournalCallStatus::from($data['status']) : $call->status,
            'topics' => $data['topics'] ?? $call->topics,
        ]);

        if (array_key_exists('editorial_board_member_ids', $data)) {
            $this->syncBoardMembers($call, $data['editorial_board_member_ids'] ?? []);
        }

        return $call->fresh(['editorialBoardMembers', 'issue.volume']);
    }

    public function delete(JournalCallForPapers $call): void
    {
        $call->delete();
    }

    public function paginateAdmin(int $perPage = 20): LengthAwarePaginator
    {
        return JournalCallForPapers::query()
            ->withCount('submissions')
            ->with(['editorialBoardMembers', 'issue.volume'])
            ->latest()
            ->paginate($perPage)
            ->through(fn (JournalCallForPapers $call) => $this->present($call, detailed: true));
    }

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    public function listOpen()
    {
        return JournalCallForPapers::query()
            ->where('status', JournalCallStatus::Open)
            ->with(['editorialBoardMembers', 'issue.volume'])
            ->latest('opens_at')
            ->get()
            ->filter(fn (JournalCallForPapers $call) => $call->isOpen())
            ->map(fn (JournalCallForPapers $call) => $this->present($call, detailed: true))
            ->values();
    }

    public function findByUuid(string $uuid, bool $detailed = false): ?array
    {
        $call = JournalCallForPapers::query()
            ->where('uuid', $uuid)
            ->with(['editorialBoardMembers', 'issue.volume'])
            ->first();

        return $call ? $this->present($call, detailed: $detailed) : null;
    }

    public function findOpenByUuid(string $uuid): ?JournalCallForPapers
    {
        $call = JournalCallForPapers::query()
            ->where('uuid', $uuid)
            ->with(['editorialBoardMembers', 'issue.volume'])
            ->first();

        return ($call && $call->isOpen()) ? $call : null;
    }

    /** @param array<int, int|string> $memberIds */
    private function syncBoardMembers(JournalCallForPapers $call, array $memberIds): void
    {
        $ids = EditorialBoardMember::query()
            ->whereIn('uuid', $memberIds)
            ->orWhereIn('id', $memberIds)
            ->pluck('id');

        $call->editorialBoardMembers()->sync($ids);
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $slug = Str::slug($value);
        $base = $slug;
        $counter = 1;

        while (JournalCallForPapers::query()
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    /** @return array<string, mixed> */
    public function present(JournalCallForPapers $call, bool $detailed = false): array
    {
        $data = [
            'uuid' => $call->uuid,
            'title' => $call->title,
            'slug' => $call->slug,
            'excerpt' => $call->excerpt,
            'opens_at' => $call->opens_at?->toIso8601String(),
            'closes_at' => $call->closes_at?->toIso8601String(),
            'submission_fee' => (float) $call->submission_fee,
            'publication_fee' => (float) $call->publication_fee,
            'currency' => $call->currency,
            'status' => $call->status->value,
            'is_open' => $call->isOpen(),
            'topics' => $call->topics ?? [],
            'submissions_count' => $call->submissions_count ?? null,
            'issue' => $call->relationLoaded('issue') && $call->issue
                ? $this->issueService->present($call->issue, detailed: true)
                : null,
        ];

        if ($detailed) {
            $data['body'] = $call->body;
            $data['editorial_board'] = $call->relationLoaded('editorialBoardMembers')
                ? $call->editorialBoardMembers->map(fn (EditorialBoardMember $member) => app(EditorialBoardService::class)->present($member))->values()->all()
                : [];
        }

        return $data;
    }

    public function assertSubmittable(?JournalCallForPapers $call): void
    {
        if (! $call || ! $call->isOpen()) {
            throw ValidationException::withMessages([
                'call_for_papers_uuid' => ['Selected call for papers is not open for submissions.'],
            ]);
        }
    }
}
