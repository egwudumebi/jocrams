<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\SaveProfessionalDetailsRequest;
use App\Http\Requests\Onboarding\StartOnboardingRequest;
use App\Http\Requests\Onboarding\SubmitOnboardingRequest;
use App\Http\Requests\Onboarding\VerifyOnboardingEmailRequest;
use App\Services\Membership\OnboardingService;
use Illuminate\Http\JsonResponse;

class OnboardingController extends Controller
{
    public function __construct(private readonly OnboardingService $onboardingService) {}

    public function start(StartOnboardingRequest $request): JsonResponse
    {
        $result = $this->onboardingService->start($request->validated());

        return response()->json($result, 201);
    }

    public function verifyEmail(VerifyOnboardingEmailRequest $request): JsonResponse
    {
        $result = $this->onboardingService->verifyEmail(
            $request->input('session_id'),
            $request->input('otp'),
        );

        return response()->json($result);
    }

    public function saveProfessionalDetails(SaveProfessionalDetailsRequest $request): JsonResponse
    {
        $payload = $request->safe()->except('supporting_documents');
        $files = $request->file('supporting_documents', []) ?? [];

        $result = $this->onboardingService->saveProfessionalDetails(
            $request->input('session_id'),
            $payload,
            is_array($files) ? $files : [$files],
        );

        return response()->json($result);
    }

    public function submit(SubmitOnboardingRequest $request): JsonResponse
    {
        $result = $this->onboardingService->submit($request->input('session_id'));

        return response()->json($result);
    }
}
