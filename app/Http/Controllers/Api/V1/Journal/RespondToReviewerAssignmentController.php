<?php

namespace App\Http\Controllers\Api\V1\Journal;

use App\Http\Controllers\Controller;
use App\Services\Journal\JournalSubmissionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class RespondToReviewerAssignmentController extends Controller
{
    public function __invoke(string $submissionId, Request $request, JournalSubmissionService $service): JsonResponse
    {
        $data = $request->validate([
            'assignment_id' => ['required', 'uuid'],
            'decision' => ['required', 'string', 'in:accept,decline'],
        ]);

        try {
            $service->respondToAssignment(
                reviewer: $request->user(),
                submissionIdentifier: $submissionId,
                assignmentId: $data['assignment_id'],
                decision: $data['decision'],
            );
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Assignment response recorded.']);
    }
}
