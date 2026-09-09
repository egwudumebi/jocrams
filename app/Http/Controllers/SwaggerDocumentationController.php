<?php

namespace App\Http\Controllers;

use App\Support\OpenApi\OpenApiSpecFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class SwaggerDocumentationController extends Controller
{
    public function __construct(
        private readonly OpenApiSpecFactory $openApi,
    ) {}

    public function ui(): View
    {
        return view('docs.swagger', [
            'specUrl' => url(config('openapi.json_path', 'docs/openapi.json')),
            'title' => config('openapi.title', 'API Documentation'),
        ]);
    }

    public function json(): JsonResponse
    {
        return response()->json(
            $this->openApi->build(),
            200,
            [],
            JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
        );
    }
}
