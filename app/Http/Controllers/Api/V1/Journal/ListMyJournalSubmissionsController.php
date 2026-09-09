<?php

namespace App\Http\Controllers\Api\V1\Journal;

use App\Http\Controllers\Controller;
use App\Services\Journal\JournalDocumentAccessResolver;
use App\Services\Journal\JournalSubmissionRepository;
use App\Support\Http\ListPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListMyJournalSubmissionsController extends Controller
{
    public function __invoke(
        Request $request,
        JournalSubmissionRepository $repository,
    ): JsonResponse {
        $perPage = ListPagination::perPage($request);

        $paginator = $repository->paginateFindByAuthorIdDetailed(
            (string) $request->user()->uuid,
            $perPage,
        );

        return ListPagination::fromPaginator($paginator);
    }
}
