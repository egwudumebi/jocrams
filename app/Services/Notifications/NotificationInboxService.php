<?php

namespace App\Services\Notifications;

use App\Enums\NotificationChannel;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NotificationInboxService
{
    public function paginateForUser(User $user, int $perPage = 25): LengthAwarePaginator
    {
        $perPage = max(1, min($perPage, 100));

        return $this->baseQuery($user)
            ->latest()
            ->paginate($perPage)
            ->through(fn (NotificationLog $log) => $this->toArray($log));
    }

    public function unreadCount(User $user): int
    {
        return $this->baseQuery($user)
            ->whereNull('read_at')
            ->count();
    }

    public function markAsRead(User $user, NotificationLog $log): NotificationLog
    {
        abort_unless($this->belongsToUser($user, $log), 404);

        if ($log->read_at === null) {
            $log->update(['read_at' => now()]);
        }

        return $log->fresh();
    }

    public function markAllAsRead(User $user): int
    {
        return $this->baseQuery($user)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /** @return array<string, mixed> */
    private function toArray(NotificationLog $log): array
    {
        return [
            'id' => $log->id,
            'event' => $log->event,
            'subject' => $log->subject,
            'body' => $log->body,
            'read_at' => $log->read_at?->toIso8601String(),
            'created_at' => $log->created_at?->toIso8601String(),
        ];
    }

    private function baseQuery(User $user)
    {
        return NotificationLog::query()
            ->where('channel', NotificationChannel::InApp)
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->getKey());
    }

    private function belongsToUser(User $user, NotificationLog $log): bool
    {
        return $log->channel === NotificationChannel::InApp
            && $log->notifiable_type === $user->getMorphClass()
            && (int) $log->notifiable_id === (int) $user->getKey();
    }
}
