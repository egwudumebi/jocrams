<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Services\Events\EventMembershipVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerifyMembershipNumberController extends Controller
{
    public function __invoke(Request $request, EventMembershipVerifier $verifier): JsonResponse
    {
        $request->validate([
            'membership_number' => ['required', 'string', 'max:32'],
        ]);

        return response()->json([
            'data' => $verifier->presentForPublic($request->input('membership_number')),
        ]);
    }
}
