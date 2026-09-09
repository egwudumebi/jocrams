<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\MembershipTier;
use Illuminate\Http\JsonResponse;

class MembershipTierController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => MembershipTier::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(),
        ]);
    }
}
