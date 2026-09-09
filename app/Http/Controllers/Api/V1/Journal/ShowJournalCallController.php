<?php

namespace App\Http\Controllers\Api\V1\Journal;

use App\Http\Controllers\Controller;
use App\Models\JournalCallForPapers;
use App\Services\Journal\JournalCallForPapersService;
use Illuminate\Http\JsonResponse;

class ShowJournalCallController extends Controller
{
    public function __invoke(JournalCallForPapers $callForPaper, JournalCallForPapersService $service): JsonResponse
    {
        abort_unless($callForPaper->isOpen(), 404);

        $callForPaper->load('editorialBoardMembers');

        return response()->json(['data' => $service->present($callForPaper, detailed: true)]);
    }
}
