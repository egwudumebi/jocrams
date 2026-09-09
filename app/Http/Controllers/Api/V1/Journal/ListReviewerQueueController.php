<?php

namespace App\Http\Controllers\Api\V1\Journal;

use App\Http\Controllers\Controller;
use App\Services\Journal\JournalSubmissionRepository;
use App\Services\Journal\JournalSubmissionService;
use App\Support\Http\ListPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListReviewerQueueController extends Controller
{
    public function __invoke(
        Request $request,
        JournalSubmissionRepository $repository,
    ): JsonResponse {
        $reviewerId = $request->user()->canJournalAssign()
            ? null
            : (string) $request->user()->uuid;

        $paginator = $repository->paginateQueue(
            $reviewerId,
            ListPagination::perPage($request),
        );

        return ListPagination::fromPaginator($paginator);
    }
}
