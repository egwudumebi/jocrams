<?php

namespace App\Http\Controllers;

use App\Enums\PublishedArticleStatus;
use App\Models\PublishedArticle;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublishedArticlePdfController extends Controller
{
    public function __invoke(string $slug): BinaryFileResponse
    {
        $article = PublishedArticle::query()
            ->where('slug', $slug)
            ->where('status', PublishedArticleStatus::Published)
            ->firstOrFail();

        abort_unless($article->hasPdf(), 404, 'PDF not available for this article.');

        $filename = $article->pdf_original_filename
            ?: ($article->slug.'.pdf');

        $path = Storage::disk('local')->path($article->pdf_path);

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
