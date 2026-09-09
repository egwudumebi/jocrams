<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Services\Fees\FeeCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeeCatalogController extends Controller
{
    public function __construct(private readonly FeeCatalogService $service) {}

    public function __invoke(Request $request): JsonResponse
    {
        $items = $this->service->listActive(
            category: $request->query('category'),
            feeType: $request->query('fee_type'),
        );

        return response()->json(['data' => $items->values()]);
    }
}
