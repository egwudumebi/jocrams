<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Services\Notifications\NotificationInboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationInboxService $notificationInboxService) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->notificationInboxService->paginateForUser(
            $request->user(),
            (int) $request->input('per_page', 25),
        );

        return response()->json([
            ...$paginator->toArray(),
            'unread_count' => $this->notificationInboxService->unreadCount($request->user()),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'unread_count' => $this->notificationInboxService->unreadCount($request->user()),
        ]);
    }

    public function markRead(Request $request, NotificationLog $notificationLog): JsonResponse
    {
        $log = $this->notificationInboxService->markAsRead($request->user(), $notificationLog);

        return response()->json(['data' => [
            'id' => $log->id,
            'read_at' => $log->read_at?->toIso8601String(),
        ]]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $updated = $this->notificationInboxService->markAllAsRead($request->user());

        return response()->json([
            'message' => 'Notifications marked as read.',
            'updated' => $updated,
        ]);
    }
}
