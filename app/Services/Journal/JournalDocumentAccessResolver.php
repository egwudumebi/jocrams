<?php

namespace App\Services\Journal;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

class JournalDocumentAccessResolver
{
    /** @return array{requester_id: string|null, can_manage_journal: bool, can_publish: bool, is_member: bool} */
    public function contextFromUser(?Authenticatable $user): array
    {
        if (! $user instanceof User) {
            return [
                'requester_id' => null,
                'can_manage_journal' => false,
                'can_publish' => false,
                'is_member' => false,
            ];
        }

        return [
            'requester_id' => (string) $user->uuid,
            'can_manage_journal' => $user->canJournalAssign(),
            'can_publish' => $user->canJournalPublish(),
            'is_member' => $user->isActiveMember(),
        ];
    }

    public function canAccessDocument(
        string $authorId,
        string $status,
        string $visibility,
        ?string $requesterId,
        bool $canManageJournal,
        bool $isMember,
    ): bool {
        $isOwner = $requesterId !== null && $authorId === $requesterId;
        $isApprovedAndPublic = $status === 'approved' && $visibility === 'all';
        $isApprovedAndMembersOnly = $status === 'approved' && $visibility === 'members_only';

        return $isOwner
            || $canManageJournal
            || $isApprovedAndPublic
            || ($isApprovedAndMembersOnly && $isMember);
    }
}
