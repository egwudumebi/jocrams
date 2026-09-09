<?php

namespace App\Http\Controllers\Api\V1\Journal;

use App\Http\Controllers\Controller;
use App\Services\Journal\JournalSubmissionService;
use App\Support\Journal\JournalManuscriptRules;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ResubmitRevisionController extends Controller
{
    public function __invoke(string $submissionId, Request $request, JournalSubmissionService $service): JsonResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'author_name' => ['nullable', 'string', 'max:150'],
            'author_email' => ['nullable', 'email:rfc,dns', 'max:255'],
            'abstract' => ['nullable', 'string', 'max:5000'],
            'category' => ['nullable', 'string', 'max:120'],
            'keywords' => ['nullable', 'string', 'max:500'],
            'note' => ['nullable', 'string', 'max:5000'],
            'document' => JournalManuscriptRules::document(),
        ]);

        $document = $request->file('document');

        try {
            $service->resubmit(
                author: $request->user(),
                submissionIdentifier: $submissionId,
                title: $data['title'] ?? null,
                authorName: $data['author_name'] ?? null,
                authorEmail: $data['author_email'] ?? null,
                abstract: $data['abstract'] ?? null,
                category: $data['category'] ?? null,
                keywords: $data['keywords'] ?? null,
                note: $data['note'] ?? null,
                documentOriginalName: (string) $document->getClientOriginalName(),
                documentContent: (string) file_get_contents($document->getRealPath()),
            );
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Revision resubmitted.']);
    }
}
