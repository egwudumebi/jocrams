<?php

namespace App\Http\Controllers\Api\V1\Journal;

use App\Http\Controllers\Controller;
use App\Services\Journal\JournalDocumentAccessResolver;
use App\Services\Journal\JournalSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DownloadJournalDocumentController extends Controller
{
    public function __invoke(
        string $submissionId,
        Request $request,
        JournalSubmissionService $service,
        JournalDocumentAccessResolver $accessResolver,
    ): JsonResponse|\Symfony\Component\HttpFoundation\Response {
        $access = $accessResolver->contextFromUser($request->user());
        $variant = $request->query('variant', 'manuscript');

        $result = $service->downloadDocument(
            submissionIdentifier: $submissionId,
            requesterId: $access['requester_id'],
            canManageJournal: $access['can_manage_journal'],
            canPublish: $access['can_publish'],
            isMember: $access['is_member'],
            variant: is_string($variant) ? $variant : 'manuscript',
        );

        if (($result['forbidden'] ?? false) === true) {
            $message = $access['requester_id'] === null
                ? 'You do not have permission to access this document. Sign in and send your access token, or use an account with an active membership.'
                : 'You do not have permission to access this document. An active membership, author access, or journal admin privileges are required.';

            return response()->json(['message' => $message], 403);
        }

        if (($result['found'] ?? false) === false) {
            return response()->json(['message' => 'Submission not found.'], 404);
        }

        if (! Storage::disk('local')->exists((string) $result['path'])) {
            return response()->json(['message' => 'Document file is unavailable.'], 404);
        }

        return response($result['file'], 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="'.basename((string) $result['path']).'"',
        ]);
    }
}
