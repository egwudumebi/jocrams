<?php

namespace App\Http\Controllers\Api\V1\Journal;

use App\Http\Controllers\Controller;
use App\Services\Journal\JournalSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SetJournalVisibilityController extends Controller
{
    public function __invoke(string $submissionId, Request $request, JournalSubmissionService $service): JsonResponse
    {
        $data = $request->validate([
            'visibility' => ['required', 'string', 'in:all,members_only'],
        ]);

        $service->setVisibility($submissionId, $data['visibility']);

        return response()->json(['message' => 'Visibility updated.']);
    }
}
