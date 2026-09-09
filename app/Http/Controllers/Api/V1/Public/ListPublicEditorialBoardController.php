<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Services\Journal\EditorialBoardService;
use Illuminate\Http\JsonResponse;

class ListPublicEditorialBoardController extends Controller
{
    public function __invoke(EditorialBoardService $service): JsonResponse
    {
        return response()->json(['data' => $service->listActive()]);
    }
}
