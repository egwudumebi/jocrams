<?php

namespace App\Http\Controllers\Api\V1\Journal;

use App\Http\Controllers\Controller;
use App\Services\Journal\JournalSubmissionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class AddEditorialCommentController extends Controller
{
    public function __invoke(string $submissionId, Request $request, JournalSubmissionService $service): JsonResponse
    {
        $data = $request->validate([
            'comment' => ['required', 'string', 'max:5000'],
            'author_role' => ['required', 'string', 'in:author,reviewer,editor'],
            'parent_id' => ['nullable', 'uuid'],
        ]);

        try {
            $service->addComment(
                author: $request->user(),
                submissionIdentifier: $submissionId,
                authorRole: $data['author_role'],
                comment: $data['comment'],
                parentId: $data['parent_id'] ?? null,
            );
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Comment added.'], 201);
    }
}
