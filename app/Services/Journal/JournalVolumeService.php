<?php

namespace App\Services\Journal;

use App\Enums\JournalVolumeStatus;
use App\Models\JournalVolume;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class JournalVolumeService
{
    /** @param array<string, mixed> $data */
    public function create(array $data): JournalVolume
    {
        return JournalVolume::query()->create([
            'title' => $data['title'],
            'volume_number' => $data['volume_number'],
            'year' => $data['year'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => JournalVolumeStatus::from($data['status'] ?? JournalVolumeStatus::Draft->value),
            'sort_order' => $data['sort_order'] ?? 0,
            'published_at' => ($data['status'] ?? null) === JournalVolumeStatus::Published->value ? now() : null,
        ]);
    }

    /** @param array<string, mixed> $data */
    public function update(JournalVolume $volume, array $data): JournalVolume
    {
        $status = isset($data['status']) ? JournalVolumeStatus::from($data['status']) : $volume->status;

        $volume->update([
            'title' => $data['title'] ?? $volume->title,
            'volume_number' => $data['volume_number'] ?? $volume->volume_number,
            'year' => $data['year'] ?? $volume->year,
            'description' => $data['description'] ?? $volume->description,
            'status' => $status,
            'sort_order' => $data['sort_order'] ?? $volume->sort_order,
            'published_at' => $status === JournalVolumeStatus::Published && ! $volume->published_at
                ? now()
                : $volume->published_at,
        ]);

        return $volume->fresh(['issues']);
    }

    public function delete(JournalVolume $volume): void
    {
        $volume->delete();
    }

    public function paginateAdmin(int $perPage = 50): LengthAwarePaginator
    {
        return JournalVolume::query()
            ->withCount('issues')
            ->orderByDesc('year')
            ->orderByDesc('volume_number')
            ->paginate($perPage)
            ->through(fn (JournalVolume $volume) => $this->present($volume, detailed: true));
    }

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    public function listForSelect()
    {
        return JournalVolume::query()
            ->with(['issues' => fn ($query) => $query->orderBy('sort_order')->orderBy('issue_number')])
            ->orderByDesc('year')
            ->orderByDesc('volume_number')
            ->get()
            ->map(fn (JournalVolume $volume) => $this->present($volume, detailed: true));
    }

    /** @return array<string, mixed> */
    public function present(JournalVolume $volume, bool $detailed = false): array
    {
        $data = [
            'uuid' => $volume->uuid,
            'title' => $volume->title,
            'volume_number' => $volume->volume_number,
            'year' => $volume->year,
            'description' => $volume->description,
            'status' => $volume->status->value,
            'sort_order' => $volume->sort_order,
            'published_at' => $volume->published_at?->toIso8601String(),
            'label' => $volume->label(),
            'issues_count' => $volume->issues_count ?? null,
        ];

        if ($detailed && $volume->relationLoaded('issues')) {
            $issueService = app(JournalIssueService::class);
            $data['issues'] = $volume->issues->map(fn ($issue) => $issueService->present($issue))->values()->all();
        }

        return $data;
    }
}
