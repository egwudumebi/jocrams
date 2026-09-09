<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\Journal\JournalSubmissionCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JournalSubmissionCategoryController extends Controller
{
    public function __construct(private readonly JournalSubmissionCategoryService $service) {}

    public function index(): JsonResponse
    {
        return response()->json($this->service->paginateAdmin());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $category = $this->service->create($data);

        return response()->json(['data' => $this->service->present($category)], 201);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $category = $this->service->update($category, $data);

        return response()->json(['data' => $this->service->present($category)]);
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->service->delete($category);

        return response()->json(['message' => 'Category deleted.']);
    }
}
