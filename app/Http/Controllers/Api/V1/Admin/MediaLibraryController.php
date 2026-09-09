<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMediaLibraryItemRequest;
use App\Models\MediaFile;
use App\Services\Content\MediaLibraryService;
use App\Support\Content\MediaCatalogPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaLibraryController extends Controller
{
    public function __construct(private readonly MediaLibraryService $mediaLibraryService) {}

    public function index(Request $request): JsonResponse
    {
        $query = MediaFile::query()
            ->with('uploader')
            ->where('collection', 'media-catalog')
            ->latest();

        if ($request->filled('file_type')) {
            $query->where('metadata->file_type', $request->input('file_type'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($builder) use ($search): void {
                $builder->where('original_filename', 'like', "%{$search}%")
                    ->orWhere('metadata->title', 'like', "%{$search}%");
            });
        }

        $paginator = $query->paginate(24)->through(
            fn (MediaFile $media) => MediaCatalogPresenter::toAdminArray($media),
        );

        return response()->json($paginator);
    }

    public function store(StoreMediaLibraryItemRequest $request): JsonResponse
    {
        $data = $request->validated();
        unset($data['file']);

        $media = $this->mediaLibraryService->upload(
            $request->user(),
            $data,
            $request->file('file'),
        );

        return response()->json(['data' => MediaCatalogPresenter::toAdminArray($media)], 201);
    }

    public function destroy(MediaFile $mediaFile): JsonResponse
    {
        abort_unless($mediaFile->collection === 'media-catalog', 404);

        $this->mediaLibraryService->delete($mediaFile);

        return response()->json(['message' => 'Media item removed.']);
    }
}
