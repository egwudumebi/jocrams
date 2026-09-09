<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Enums\Visibility;
use App\Http\Controllers\Controller;
use App\Models\MediaFile;
use App\Support\Content\MediaCatalogPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MediaCatalogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = MediaFile::query()
            ->where('collection', 'media-catalog')
            ->where('visibility', Visibility::Public)
            ->latest();

        if ($request->filled('file_type')) {
            $query->where('metadata->file_type', $request->input('file_type'));
        }

        $perPage = min((int) $request->input('per_page', 12), 50);
        $items = $query->get()
            ->groupBy(fn (MediaFile $media) => data_get($media->metadata, 'batch_id', $media->uuid))
            ->values()
            ->map(fn ($files) => MediaCatalogPresenter::toBatchArray($files));

        $page = max(1, (int) $request->input('page', 1));
        $total = $items->count();
        $data = $items->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ]);
    }

    public function show(MediaFile $mediaFile): JsonResponse
    {
        $this->ensureCatalogItem($mediaFile);

        return response()->json(['data' => MediaCatalogPresenter::toPublicArray($mediaFile)]);
    }

    public function view(MediaFile $mediaFile)
    {
        $this->ensureCatalogItem($mediaFile);

        return $this->stream($mediaFile, inline: true);
    }

    public function file(MediaFile $mediaFile): StreamedResponse
    {
        $this->ensureCatalogItem($mediaFile);

        return $this->stream($mediaFile, inline: false);
    }

    private function ensureCatalogItem(MediaFile $mediaFile): void
    {
        if ($mediaFile->collection !== 'media-catalog' || $mediaFile->visibility !== Visibility::Public) {
            throw new NotFoundHttpException('Media item not found.');
        }
    }

    private function stream(MediaFile $mediaFile, bool $inline): StreamedResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        if (! Storage::disk($mediaFile->disk)->exists($mediaFile->path)) {
            throw new NotFoundHttpException('Stored media is unavailable.');
        }

        $disposition = $inline ? 'inline' : 'attachment';

        return Storage::disk($mediaFile->disk)->response(
            $mediaFile->path,
            $mediaFile->original_filename,
            ['Content-Disposition' => "{$disposition}; filename=\"{$mediaFile->original_filename}\""],
        );
    }
}
