<?php

namespace App\Http\Controllers\Api\V1\Journal;

use App\Http\Controllers\Controller;
use App\Services\Journal\JournalSubmissionRepository;
use Illuminate\Http\JsonResponse;

class ListSubmissionTimelineController extends Controller
{
    public function __invoke(string $submissionId, JournalSubmissionRepository $repository): JsonResponse
    {
        $uuid = $repository->resolveSubmissionUuid($submissionId);
        if ($uuid === null) {
            return response()->json(['message' => 'Submission not found.'], 404);
        }

        return response()->json([
            'data' => $repository->timeline($uuid),
        ]);
    }
}
