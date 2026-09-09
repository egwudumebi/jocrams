<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Http\Controllers\Controller;
use App\Services\Member\MemberDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly MemberDashboardService $memberDashboardService) {}

    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->memberDashboardService->snapshot($request->user()),
        ]);
    }
}
