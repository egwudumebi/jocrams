<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\JournalVolume;
use App\Services\Journal\JournalVolumeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JournalVolumeController extends Controller
{
    public function __construct(private readonly JournalVolumeService $service) {}

    public function index(): JsonResponse
    {
        return response()->json($this->service->paginateAdmin());
    }

    public function options(): JsonResponse
    {
        return response()->json(['data' => $this->service->listForSelect()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'volume_number' => ['required', 'integer', 'min:1'],
            'year' => ['required', 'integer', 'min:1900', 'max:2100'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'in:draft,published,archived'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $volume = $this->service->create($data);

        return response()->json(['data' => $this->service->present($volume)], 201);
    }

    public function update(Request $request, JournalVolume $volume): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'volume_number' => ['sometimes', 'integer', 'min:1'],
            'year' => ['required', 'integer', 'min:1900', 'max:2100'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'in:draft,published,archived'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $volume = $this->service->update($volume, $data);

        return response()->json(['data' => $this->service->present($volume, detailed: true)]);
    }

    public function destroy(JournalVolume $volume): JsonResponse
    {
        $this->service->delete($volume);

        return response()->json(['message' => 'Volume deleted.']);
    }
}
