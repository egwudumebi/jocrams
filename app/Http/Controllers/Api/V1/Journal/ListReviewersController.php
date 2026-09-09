<?php

namespace App\Http\Controllers\Api\V1\Journal;

use App\Http\Controllers\Controller;
use App\Services\Journal\JournalSubmissionRepository;
use Illuminate\Http\JsonResponse;

class ListReviewersController extends Controller
{
    public function __invoke(JournalSubmissionRepository $repository): JsonResponse
    {
        return response()->json(['data' => $repository->listReviewers()]);
    }
}
