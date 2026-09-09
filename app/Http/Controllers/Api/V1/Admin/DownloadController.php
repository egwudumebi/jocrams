<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDownloadRequest;
use App\Http\Requests\Admin\UpdateDownloadRequest;
use App\Models\Download;
use App\Services\Library\DownloadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DownloadController extends Controller
{
    public function __construct(private readonly DownloadService $downloadService) {}

    public function index(Request $request): JsonResponse
    {
        $query = Download::query()
            ->with(['mediaFile', 'category'])
            ->latest('updated_at');

        if ($request->filled('search')) {
            $query->where('title', 'like', '%'.$request->input('search').'%');
        }

        return response()->json($query->paginate(20));
    }

    public function store(StoreDownloadRequest $request): JsonResponse
    {
        $data = $request->validated();
        unset($data['file']);

        $download = $this->downloadService->create(
            $request->user(),
            $data,
            $request->file('file'),
        );

        return response()->json(['data' => $download], 201);
    }

    public function show(Download $download): JsonResponse
    {
        return response()->json(['data' => $download->load(['mediaFile', 'category'])]);
    }

    public function update(UpdateDownloadRequest $request, Download $download): JsonResponse
    {
        $data = $request->validated();

        $download = $this->downloadService->update(
            $download,
            $request->user(),
            $data,
            $request->file('file'),
        );

        return response()->json(['data' => $download]);
    }

    public function destroy(Download $download): JsonResponse
    {
        $this->downloadService->delete($download);

        return response()->json(['message' => 'Download removed.']);
    }
}
