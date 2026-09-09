<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Reports\ReportsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    public function __construct(private readonly ReportsService $reportsService) {}

    public function overview(): JsonResponse
    {
        return response()->json([
            'data' => $this->reportsService->overview(),
        ]);
    }

    public function recentActivities(Request $request): JsonResponse
    {
        $limit = (int) $request->input('limit', 50);

        return response()->json([
            'data' => $this->reportsService->recentActivities($limit),
        ]);
    }
}
