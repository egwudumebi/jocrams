<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\JournalCallForPapers;
use App\Services\Journal\JournalCallForPapersService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JournalCallForPapersController extends Controller
{
    public function __construct(private readonly JournalCallForPapersService $service) {}

    public function index(): JsonResponse
    {
        return response()->json($this->service->paginateAdmin());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'journal_issue_uuid' => ['required', 'uuid', 'exists:journal_issues,uuid'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after_or_equal:opens_at'],
            'submission_fee' => ['nullable', 'numeric', 'min:0'],
            'publication_fee' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'status' => ['nullable', 'in:draft,open,closed'],
            'topics' => ['nullable', 'array'],
            'topics.*' => ['string', 'max:120'],
            'editorial_board_member_ids' => ['nullable', 'array'],
            'editorial_board_member_ids.*' => ['string'],
        ]);

        $call = $this->service->create($request->user(), $data);

        return response()->json(['data' => $this->service->present($call, detailed: true)], 201);
    }

    public function show(JournalCallForPapers $callForPaper): JsonResponse
    {
        $callForPaper->load('editorialBoardMembers', 'issue.volume');

        return response()->json(['data' => $this->service->present($callForPaper, detailed: true)]);
    }

    public function update(Request $request, JournalCallForPapers $callForPaper): JsonResponse
    {
        $data = $request->validate([
            'journal_issue_uuid' => ['sometimes', 'uuid', 'exists:journal_issues,uuid'],
            'title' => ['sometimes', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date'],
            'submission_fee' => ['nullable', 'numeric', 'min:0'],
            'publication_fee' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'status' => ['nullable', 'in:draft,open,closed'],
            'topics' => ['nullable', 'array'],
            'topics.*' => ['string', 'max:120'],
            'editorial_board_member_ids' => ['nullable', 'array'],
            'editorial_board_member_ids.*' => ['string'],
        ]);

        $call = $this->service->update($callForPaper, $data);

        return response()->json(['data' => $this->service->present($call, detailed: true)]);
    }

    public function destroy(JournalCallForPapers $callForPaper): JsonResponse
    {
        $this->service->delete($callForPaper);

        return response()->json(['message' => 'Call for papers deleted.']);
    }
}
