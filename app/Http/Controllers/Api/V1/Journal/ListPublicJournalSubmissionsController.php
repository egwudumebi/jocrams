<?php

namespace App\Http\Controllers\Api\V1\Journal;

use App\Http\Controllers\Controller;
use App\Services\Journal\JournalDocumentAccessResolver;
use App\Services\Journal\JournalSubmissionRepository;
use App\Support\Http\ListPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListPublicJournalSubmissionsController extends Controller
{
    public function __invoke(
        Request $request,
        JournalSubmissionRepository $repository,
        JournalDocumentAccessResolver $accessResolver,
    ): JsonResponse {
        $access = $accessResolver->contextFromUser($request->user());
        $perPage = ListPagination::perPage($request);

        $paginator = $repository->paginateFindAll(
            true,
            $access['requester_id'],
            $access['can_manage_journal'],
            $access['is_member'],
            $perPage,
        );

        return ListPagination::fromPaginator($paginator);
    }
}
