<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Support\Sicama\SicamaProfile;
use Illuminate\Http\JsonResponse;

class SicamaProfileController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => SicamaProfile::publicPayload()]);
    }
}
