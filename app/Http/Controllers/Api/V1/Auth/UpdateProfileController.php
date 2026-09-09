<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Services\Auth\UserProfileService;
use App\Support\Auth\UserProfilePresenter;
use Illuminate\Http\JsonResponse;

class UpdateProfileController extends Controller
{
    public function __construct(private readonly UserProfileService $profileService) {}

    public function __invoke(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $profile = $this->profileService->update($user, $request->validated());

        return response()->json([
            'message' => 'Profile updated.',
            'profile' => UserProfilePresenter::forUser($user, $profile),
        ]);
    }
}
