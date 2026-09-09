<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Support\Settings\SiteBranding;
use Illuminate\Http\JsonResponse;

class SiteBrandingController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => SiteBranding::publicPayload()]);
    }
}
