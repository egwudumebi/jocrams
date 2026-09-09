<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Support\Settings\SiteBanner;
use Illuminate\Http\JsonResponse;

class SiteBannerController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => SiteBanner::publicPayload()]);
    }
}
