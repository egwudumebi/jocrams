<?php

namespace App\Http\Controllers\Api\V1\Journal;

use App\Http\Controllers\Controller;
use App\Services\Journal\JournalSubmissionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class AssignReviewerController extends Controller
{
    public function __invoke(string $submissionId, Request $request, JournalSubmissionService $service): JsonResponse
    {
        $data = $request->validate([
            'reviewer_id' => ['required', 'uuid'],
            'priority' => ['required', 'string', 'in:low,normal,high'],
            'due_at' => ['nullable', 'date'],
        ]);

        try {
            $service->assignReviewer(
                assignedBy: $request->user(),
                submissionIdentifier: $submissionId,
                reviewerUuid: $data['reviewer_id'],
                priority: $data['priority'],
                dueAt: isset($data['due_at']) ? (string) $data['due_at'] : null,
            );
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Reviewer assigned.']);
    }
}
