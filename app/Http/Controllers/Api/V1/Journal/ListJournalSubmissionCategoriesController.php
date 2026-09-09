<?php

namespace App\Http\Controllers\Api\V1\Journal;

use App\Http\Controllers\Controller;
use App\Services\Journal\JournalSubmissionCategoryService;
use Illuminate\Http\JsonResponse;

class ListJournalSubmissionCategoriesController extends Controller
{
    public function __invoke(JournalSubmissionCategoryService $service): JsonResponse
    {
        return response()->json(['data' => $service->listActive()]);
    }
}
