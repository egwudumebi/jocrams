<?php

namespace App\Http\Controllers\Api\V1\Journal;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\Admin\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RemoveReviewerController extends Controller
{
    public function __invoke(string $reviewerId, Request $request, ActivityLogService $activityLog): JsonResponse
    {
        $user = User::query()->where('uuid', $reviewerId)->first();

        if ($user === null) {
            return response()->json(['message' => 'Reviewer not found.'], 404);
        }

        if (! $user->hasRole('reviewer')) {
            return response()->json([
                'message' => 'This account is listed because of editorial permissions, not a dedicated reviewer role. Remove it from admin roles instead.',
            ], 422);
        }

        $activeAssignments = DB::table('journal_reviewer_assignments')
            ->where('reviewer_id', $user->uuid)
            ->whereIn('status', ['assigned', 'accepted'])
            ->count();

        if ($activeAssignments > 0) {
            return response()->json([
                'message' => "This reviewer still has {$activeAssignments} active assignment(s). Reassign or complete those reviews before removing them.",
            ], 422);
        }

        $reviewerRole = Role::query()->where('slug', 'reviewer')->first();
        if ($reviewerRole === null) {
            return response()->json(['message' => 'Reviewer role not found.'], 422);
        }

        $user->removeRole($reviewerRole);

        $activityLog->log($request->user(), 'journal.reviewer.removed', properties: [
            'reviewer_id' => (string) $user->uuid,
            'reviewer_email' => $user->email,
        ]);

        return response()->json([
            'message' => 'Reviewer access removed. Their login account was kept.',
        ]);
    }
}
