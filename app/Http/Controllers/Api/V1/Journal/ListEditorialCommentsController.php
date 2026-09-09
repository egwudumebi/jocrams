<?php

namespace App\Http\Controllers\Api\V1\Journal;

use App\Http\Controllers\Controller;
use App\Services\Journal\JournalSubmissionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class ListEditorialCommentsController extends Controller
{
    public function __invoke(string $submissionId, JournalSubmissionService $service): JsonResponse
    {
        try {
            $comments = $service->commentsFor(request()->user(), $submissionId);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return response()->json(['data' => $comments]);
    }
}
