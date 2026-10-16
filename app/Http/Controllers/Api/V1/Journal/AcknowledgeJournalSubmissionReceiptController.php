<?php

namespace App\Http\Controllers\Api\V1\Journal;

use App\Http\Controllers\Controller;
use App\Services\Journal\JournalSubmissionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class AcknowledgeJournalSubmissionReceiptController extends Controller
{
    public function __invoke(string $submissionId, Request $request, JournalSubmissionService $service): JsonResponse
    {
        try {
            $service->acknowledgeReceipt(
                actor: $request->user(),
                submissionIdentifier: $submissionId,
            );
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Receipt acknowledged. The author has been emailed.']);
    }
}
