<?php

namespace App\Http\Controllers\Api\V1\Journal;

use App\Http\Controllers\Controller;
use App\Services\Journal\JournalCallForPapersService;
use Illuminate\Http\JsonResponse;

class ListOpenJournalCallsController extends Controller
{
    public function __invoke(JournalCallForPapersService $service): JsonResponse
    {
        return response()->json(['data' => $service->listOpen()]);
    }
}
