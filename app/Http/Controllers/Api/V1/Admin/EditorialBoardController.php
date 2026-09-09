<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Enums\EditorialBoardRole;
use App\Models\EditorialBoardMember;
use App\Services\Journal\EditorialBoardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EditorialBoardController extends Controller
{
    public function __construct(private readonly EditorialBoardService $service) {}

    public function index(): JsonResponse
    {
        return response()->json($this->service->paginateAdmin());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'uuid', 'exists:users,uuid'],
            'name' => ['required', 'string', 'max:150'],
            'role_title' => ['required', 'string', Rule::in(EditorialBoardRole::values())],
            'affiliation' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $member = $this->service->create($data);

        return response()->json(['data' => $this->service->present($member)], 201);
    }

    public function update(Request $request, EditorialBoardMember $editorialBoardMember): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'uuid', 'exists:users,uuid'],
            'name' => ['sometimes', 'string', 'max:150'],
            'role_title' => ['sometimes', 'string', Rule::in(EditorialBoardRole::values())],
            'affiliation' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $member = $this->service->update($editorialBoardMember, $data);

        return response()->json(['data' => $this->service->present($member)]);
    }

    public function destroy(EditorialBoardMember $editorialBoardMember): JsonResponse
    {
        $this->service->delete($editorialBoardMember);

        return response()->json(['message' => 'Editorial board member removed.']);
    }
}
