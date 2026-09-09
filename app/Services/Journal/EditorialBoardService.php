<?php

namespace App\Services\Journal;

use App\Models\EditorialBoardMember;
use App\Models\User;
use App\Support\Auth\ProfileImageUrl;
use App\Support\Html\HtmlSanitizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EditorialBoardService
{
    /** @param array<string, mixed> $data */
    public function create(array $data): EditorialBoardMember
    {
        return EditorialBoardMember::query()->create([
            'user_id' => $data['user_id'] ?? null,
            'name' => $data['name'],
            'role_title' => $data['role_title'],
            'affiliation' => $data['affiliation'] ?? null,
            'bio' => HtmlSanitizer::clean($data['bio'] ?? null),
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /** @param array<string, mixed> $data */
    public function update(EditorialBoardMember $member, array $data): EditorialBoardMember
    {
        $member->update([
            'user_id' => $data['user_id'] ?? $member->user_id,
            'name' => $data['name'] ?? $member->name,
            'role_title' => $data['role_title'] ?? $member->role_title,
            'affiliation' => $data['affiliation'] ?? $member->affiliation,
            'bio' => array_key_exists('bio', $data) ? HtmlSanitizer::clean($data['bio']) : $member->bio,
            'sort_order' => $data['sort_order'] ?? $member->sort_order,
            'is_active' => $data['is_active'] ?? $member->is_active,
        ]);

        return $member->fresh(['user.profile']);
    }

    public function delete(EditorialBoardMember $member): void
    {
        $member->delete();
    }

    public function paginateAdmin(int $perPage = 50): LengthAwarePaginator
    {
        return EditorialBoardMember::query()
            ->with('user.profile')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage)
            ->through(fn (EditorialBoardMember $member) => $this->present($member));
    }

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    public function listActive()
    {
        return EditorialBoardMember::query()
            ->where('is_active', true)
            ->with('user.profile')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (EditorialBoardMember $member) => $this->present($member));
    }

    /** @return array<string, mixed> */
    public function present(EditorialBoardMember $member): array
    {
        $profileImage = null;

        if ($member->user_id && $member->relationLoaded('user') && $member->user) {
            $profileImage = ProfileImageUrl::forUser(
                (string) $member->user->uuid,
                $member->user->profile?->profile_image_path,
            );
        }

        return [
            'uuid' => $member->uuid,
            'user_id' => $member->user_id,
            'name' => $member->name,
            'role_title' => $member->role_title,
            'affiliation' => $member->affiliation,
            'bio' => $member->bio,
            'sort_order' => $member->sort_order,
            'is_active' => $member->is_active,
            'profile_image' => $profileImage,
        ];
    }

    public function resolveFromUser(User $user, string $roleTitle): EditorialBoardMember
    {
        $existing = EditorialBoardMember::query()->where('user_id', $user->uuid)->first();

        if ($existing) {
            return $existing;
        }

        return $this->create([
            'user_id' => $user->uuid,
            'name' => $user->name,
            'role_title' => $roleTitle,
            'affiliation' => null,
            'bio' => null,
            'sort_order' => 0,
            'is_active' => true,
        ]);
    }
}
