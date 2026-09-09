<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Http\Controllers\Controller;
use App\Http\Requests\Membership\CreateApplicationRequest;
use App\Http\Requests\Membership\UploadDocumentRequest;
use App\Models\MembershipApplication;
use App\Models\MembershipTier;
use App\Services\Membership\MembershipApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MembershipApplicationController extends Controller
{
    public function __construct(private readonly MembershipApplicationService $applicationService) {}

    public function index(Request $request): JsonResponse
    {
        $applications = MembershipApplication::query()
            ->where('user_id', $request->user()->id)
            ->with(['tier', 'documents.mediaFile', 'approval'])
            ->latest()
            ->get();

        return response()->json(['data' => $applications]);
    }

    public function store(CreateApplicationRequest $request): JsonResponse
    {
        $tier = MembershipTier::query()->findOrFail($request->input('membership_tier_id'));

        $application = $this->applicationService->createApplication(
            $request->user(),
            $tier,
            $request->input('form_data', []),
        );

        return response()->json(['data' => $application->load('tier')], 201);
    }

    public function show(MembershipApplication $application): JsonResponse
    {
        $this->authorizeApplication($application);

        return response()->json(['data' => $application->load(['tier', 'documents.mediaFile', 'approval'])]);
    }

    public function uploadDocument(UploadDocumentRequest $request, MembershipApplication $application): JsonResponse
    {
        $this->authorizeApplication($application);

        $document = $this->applicationService->attachDocument(
            $application,
            $request->file('document'),
            $request->input('document_type'),
            $request->user(),
        );

        return response()->json(['data' => $document->load('mediaFile')], 201);
    }

    public function submit(MembershipApplication $application): JsonResponse
    {
        $this->authorizeApplication($application);

        $application = $this->applicationService->submit($application);

        return response()->json(['data' => $application]);
    }

    private function authorizeApplication(MembershipApplication $application): void
    {
        abort_if(
            auth()->id() !== $application->user_id,
            403,
            'You cannot access this application.',
        );
    }
}
