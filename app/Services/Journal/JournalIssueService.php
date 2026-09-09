<?php

namespace App\Services\Journal;

use App\Enums\JournalIssueStatus;
use App\Models\JournalIssue;
use App\Models\JournalVolume;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class JournalIssueService
{
    /** @param array<string, mixed> $data */
    public function create(array $data): JournalIssue
    {
        $volume = $this->resolveVolume($data['journal_volume_uuid'] ?? $data['journal_volume_id'] ?? null);

        return JournalIssue::query()->create([
            'journal_volume_id' => $volume->id,
            'title' => $data['title'],
            'issue_number' => $data['issue_number'],
            'description' => $data['description'] ?? null,
            'status' => JournalIssueStatus::from($data['status'] ?? JournalIssueStatus::Draft->value),
            'sort_order' => $data['sort_order'] ?? 0,
            'published_at' => ($data['status'] ?? null) === JournalIssueStatus::Published->value ? now() : null,
        ]);
    }

    /** @param array<string, mixed> $data */
    public function update(JournalIssue $issue, array $data): JournalIssue
    {
        $status = isset($data['status']) ? JournalIssueStatus::from($data['status']) : $issue->status;

        $updates = [
            'title' => $data['title'] ?? $issue->title,
            'issue_number' => $data['issue_number'] ?? $issue->issue_number,
            'description' => $data['description'] ?? $issue->description,
            'status' => $status,
            'sort_order' => $data['sort_order'] ?? $issue->sort_order,
            'published_at' => $status === JournalIssueStatus::Published && ! $issue->published_at
                ? now()
                : $issue->published_at,
        ];

        if (isset($data['journal_volume_uuid']) || isset($data['journal_volume_id'])) {
            $updates['journal_volume_id'] = $this->resolveVolume($data['journal_volume_uuid'] ?? $data['journal_volume_id'])->id;
        }

        $issue->update($updates);

        return $issue->fresh(['volume']);
    }

    public function delete(JournalIssue $issue): void
    {
        $issue->delete();
    }

    public function paginateAdmin(int $perPage = 50): LengthAwarePaginator
    {
        return JournalIssue::query()
            ->with('volume')
            ->withCount(['callsForPapers', 'submissions'])
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through(fn (JournalIssue $issue) => $this->present($issue, detailed: true));
    }

    public function resolveByUuid(string $uuid): JournalIssue
    {
        $issue = JournalIssue::query()->where('uuid', $uuid)->first();

        if (! $issue) {
            throw ValidationException::withMessages([
                'journal_issue_uuid' => ['Selected journal issue was not found.'],
            ]);
        }

        return $issue;
    }

    /** @return array<string, mixed> */
    public function present(JournalIssue $issue, bool $detailed = false): array
    {
        $data = [
            'uuid' => $issue->uuid,
            'title' => $issue->title,
            'issue_number' => $issue->issue_number,
            'description' => $issue->description,
            'status' => $issue->status->value,
            'sort_order' => $issue->sort_order,
            'published_at' => $issue->published_at?->toIso8601String(),
            'label' => $issue->label(),
            'calls_count' => $issue->calls_for_papers_count ?? null,
            'submissions_count' => $issue->submissions_count ?? null,
        ];

        if ($issue->relationLoaded('volume') && $issue->volume) {
            $data['volume'] = app(JournalVolumeService::class)->present($issue->volume);
            $data['journal_volume_uuid'] = $issue->volume->uuid;
        }

        return $data;
    }

    private function resolveVolume(int|string|null $identifier): JournalVolume
    {
        if ($identifier === null) {
            throw ValidationException::withMessages([
                'journal_volume_uuid' => ['A journal volume is required.'],
            ]);
        }

        $volume = JournalVolume::query()
            ->when(is_numeric($identifier), fn ($q) => $q->where('id', (int) $identifier))
            ->when(! is_numeric($identifier), fn ($q) => $q->where('uuid', (string) $identifier))
            ->first();

        if (! $volume) {
            throw ValidationException::withMessages([
                'journal_volume_uuid' => ['Selected journal volume was not found.'],
            ]);
        }

        return $volume;
    }
}
