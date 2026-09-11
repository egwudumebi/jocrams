<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Services\Journal\JournalCallForPapersService;
use Illuminate\Http\JsonResponse;

class OpenJournalCallsController extends Controller
{
    public function __invoke(JournalCallForPapersService $service): JsonResponse
    {
        return response()->json([
            'data' => $service->listOpen()->map(fn (array $call) => [
                'uuid' => $call['uuid'],
                'title' => $call['title'],
                'excerpt' => $call['excerpt'],
                'closes_at' => $call['closes_at'],
                'issue' => $call['issue'] ?? null,
            ])->values(),
        ]);
    }
}
