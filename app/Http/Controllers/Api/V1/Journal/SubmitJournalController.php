<?php

namespace App\Http\Controllers\Api\V1\Journal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Journal\SubmitJournalRequest;
use App\Models\JournalCallForPapers;
use App\Services\Journal\DocumentMetadataExtractor;
use App\Services\Journal\JournalCallForPapersService;
use App\Services\Journal\JournalSubmissionCategoryService;
use App\Services\Journal\JournalSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class SubmitJournalController extends Controller
{
    public function __invoke(
        SubmitJournalRequest $request,
        JournalSubmissionService $service,
        DocumentMetadataExtractor $documentMetadataExtractor,
        JournalCallForPapersService $callService,
        JournalSubmissionCategoryService $categoryService,
    ): JsonResponse {
        $document = $request->file('document');

        $provided = [
            'title' => $request->input('title') ? (string) $request->string('title') : null,
            'author_name' => $request->input('author_name') ? (string) $request->string('author_name') : null,
            'author_email' => $request->input('author_email') ? (string) $request->string('author_email') : null,
            'abstract' => $request->input('abstract') ? (string) $request->string('abstract') : null,
            'category' => $request->input('category') ? (string) $request->string('category') : null,
            'keywords' => $request->input('keywords') ? (string) $request->string('keywords') : null,
            'mins_read' => $request->input('mins_read') ? (int) $request->integer('mins_read') : null,
            'references' => $request->input('references') ? (array) $request->input('references') : null,
        ];

        $extracted = $documentMetadataExtractor->extract($document);
        $meta = array_merge($extracted, array_filter($provided, fn ($v) => $v !== null && $v !== ''));

        Validator::make($meta, [
            'title' => ['required', 'string', 'max:255'],
            'author_name' => ['required', 'string', 'max:150'],
            'author_email' => ['required', 'email:rfc,dns', 'max:255'],
            'abstract' => ['required', 'string', 'max:5000'],
            'category' => ['required', 'string', 'max:120'],
            'keywords' => ['nullable', 'string', 'max:500'],
            'mins_read' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'references' => ['nullable', 'array'],
            'references.*' => ['string', 'max:2000'],
        ])->validate();

        $categoryService->validateSelectedCategory((string) $meta['category']);

        if ($callService->listOpen()->isEmpty()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'call_for_papers_uuid' => ['Submissions are closed. No call for papers is currently open.'],
            ]);
        }

        if (! $request->filled('call_for_papers_uuid')) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'call_for_papers_uuid' => ['Please select an open call for papers.'],
            ]);
        }

        $call = JournalCallForPapers::query()
            ->where('uuid', $request->input('call_for_papers_uuid'))
            ->with(['editorialBoardMembers', 'issue.volume'])
            ->first();

        $callService->assertSubmittable($call);

        $result = $service->submit(
            author: $request->user(),
            title: (string) $meta['title'],
            authorName: (string) $meta['author_name'],
            authorEmail: (string) $meta['author_email'],
            abstract: (string) $meta['abstract'],
            category: (string) $meta['category'],
            keywords: array_key_exists('keywords', $meta) && $meta['keywords'] !== '' ? (string) $meta['keywords'] : null,
            minsRead: array_key_exists('mins_read', $meta) && $meta['mins_read'] !== null ? (int) $meta['mins_read'] : null,
            references: array_key_exists('references', $meta) && is_array($meta['references'])
                ? array_values(array_map('strval', $meta['references']))
                : null,
            documentOriginalName: (string) $document->getClientOriginalName(),
            documentContent: (string) file_get_contents($document->getRealPath()),
            callForPapers: $call,
        );

        return response()->json([
            'message' => $result['requires_payment']
                ? 'Submission saved. Complete payment to finalize your manuscript.'
                : 'Submission created.',
            'submission_id' => $result['submission_id'],
            'requires_payment' => $result['requires_payment'],
            'submission_fee' => $result['submission_fee'],
            'publication_fee' => $result['publication_fee'],
            'currency' => $result['currency'],
        ], 201);
    }
}
