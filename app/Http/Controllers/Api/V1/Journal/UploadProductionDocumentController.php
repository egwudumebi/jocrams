<?php

namespace App\Http\Controllers\Api\V1\Journal;

use App\Http\Controllers\Controller;
use App\Services\Journal\JournalSubmissionService;
use App\Support\Journal\JournalManuscriptRules;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class UploadProductionDocumentController extends Controller
{
    public function __invoke(string $submissionId, Request $request, JournalSubmissionService $service): JsonResponse
    {
        $request->validate([
            'document' => JournalManuscriptRules::document(),
        ]);

        $document = $request->file('document');

        try {
            $service->uploadProductionDocument(
                editor: $request->user(),
                submissionIdentifier: $submissionId,
                documentOriginalName: (string) $document->getClientOriginalName(),
                documentContent: (string) file_get_contents($document->getRealPath()),
            );
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Production document uploaded.']);
    }
}
