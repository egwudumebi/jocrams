<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Services\Credentials\CredentialGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CredentialVerificationController extends Controller
{
    public function __construct(private readonly CredentialGenerationService $credentialService) {}

    public function verify(string $token, Request $request): JsonResponse
    {
        $result = $this->credentialService->verify(
            $token,
            $request->ip(),
            $request->userAgent(),
        );

        if (! $result['credential']) {
            return response()->json(['valid' => false, 'result' => 'not_found'], 404);
        }

        $credential = $result['credential'];

        return response()->json([
            'valid' => $result['valid'],
            'result' => $result['result'],
            'member' => [
                'name' => $credential->member->user->name,
                'membership_number' => $credential->member->membership_number,
                'tier' => $credential->member->tier->name,
                'title' => $credential->title,
                'issued_at' => $credential->issued_at,
                'expires_at' => $credential->expires_at,
            ],
        ]);
    }
}
