<?php

use App\Http\Controllers\PublishedArticlePdfController;
use App\Http\Controllers\SwaggerDocumentationController;
use Illuminate\Support\Facades\Route;

if (config('openapi.enabled')) {
    Route::get(config('openapi.docs_path', 'docs'), [SwaggerDocumentationController::class, 'ui'])
        ->name('docs.swagger');

    Route::get(config('openapi.json_path', 'docs/openapi.json'), [SwaggerDocumentationController::class, 'json'])
        ->name('docs.openapi');
}

Route::get('/articles/{slug}/pdf', PublishedArticlePdfController::class)
    ->where('slug', '[A-Za-z0-9\-]+')
    ->name('articles.pdf');

Route::view('/{any?}', 'app')->where('any', '.*');
