<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\JournalIssue;
use App\Services\Journal\JournalIssueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JournalIssueController extends Controller
{
    public function __construct(private readonly JournalIssueService $service) {}

    public function index(): JsonResponse
    {
        return response()->json($this->service->paginateAdmin());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'journal_volume_uuid' => ['required', 'uuid', 'exists:journal_volumes,uuid'],
            'title' => ['required', 'string', 'max:255'],
            'issue_number' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'in:draft,open,closed,published'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $issue = $this->service->create($data);

        return response()->json(['data' => $this->service->present($issue->load('volume'), detailed: true)], 201);
    }

    public function update(Request $request, JournalIssue $issue): JsonResponse
    {
        $data = $request->validate([
            'journal_volume_uuid' => ['sometimes', 'uuid', 'exists:journal_volumes,uuid'],
            'title' => ['sometimes', 'string', 'max:255'],
            'issue_number' => ['sometimes', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'in:draft,open,closed,published'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $issue = $this->service->update($issue, $data);

        return response()->json(['data' => $this->service->present($issue, detailed: true)]);
    }

    public function destroy(JournalIssue $issue): JsonResponse
    {
        $this->service->delete($issue);

        return response()->json(['message' => 'Issue deleted.']);
    }
}
