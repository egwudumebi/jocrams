<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UploadProfileImageRequest;
use App\Services\Auth\UserProfileService;
use Illuminate\Http\JsonResponse;

class UploadProfileImageController extends Controller
{
    public function __construct(private readonly UserProfileService $profileService) {}

    public function __invoke(UploadProfileImageRequest $request): JsonResponse
    {
        $profileImage = $this->profileService->uploadImage(
            $request->user(),
            $request->file('image'),
        );

        return response()->json([
            'message' => 'Profile image uploaded.',
            'profile_image' => $profileImage,
        ]);
    }
}
