<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\UserProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeleteProfileImageController extends Controller
{
    public function __construct(private readonly UserProfileService $profileService) {}

    public function __invoke(Request $request): JsonResponse
    {
        $this->profileService->removeImage($request->user());

        return response()->json([
            'message' => 'Profile image removed.',
            'profile_image' => null,
        ]);
    }
}
