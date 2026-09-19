<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Approval;
use App\Models\MembershipApplication;
use App\Services\Membership\MembershipApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApprovalController extends Controller
{
    public function __construct(private readonly MembershipApplicationService $applicationService) {}

    public function index(Request $request): JsonResponse
    {
        $approvals = Approval::query()
            ->where('status', 'pending')
            ->where('approvable_type', MembershipApplication::class)
            ->with([
                'submitter',
                'approvable.tier',
                'approvable.documents.mediaFile',
                'approvable.user',
            ])
            ->latest()
            ->paginate(20);

        $approvals->getCollection()->transform(function (Approval $approval) {
            $application = $approval->approvable;

            return [
                'uuid' => $approval->uuid,
                'type' => $approval->type,
                'status' => $approval->status instanceof \BackedEnum
                    ? $approval->status->value
                    : $approval->status,
                'created_at' => $approval->created_at?->toIso8601String(),
                'submitter' => $approval->submitter ? [
                    'name' => $approval->submitter->name,
                    'email' => $approval->submitter->email,
                ] : null,
                'application' => $application instanceof MembershipApplication
                    ? $this->applicationService->presentForAdmin($application)
                    : null,
                // Keep legacy key for older clients
                'approvable' => $application instanceof MembershipApplication
                    ? $this->applicationService->presentForAdmin($application)
                    : $application,
            ];
        });

        return response()->json($approvals);
    }

    public function show(MembershipApplication $application): JsonResponse
    {
        return response()->json([
            'data' => $this->applicationService->presentForAdmin($application),
        ]);
    }

    public function downloadDocument(MembershipApplication $application, int $document): StreamedResponse
    {
        return $this->applicationService->downloadDocument($application, $document);
    }

    public function approveMembership(MembershipApplication $application, Request $request): JsonResponse
    {
        $member = $this->applicationService->approve(
            $application,
            $request->user(),
            $request->input('notes'),
        );

        return response()->json(['data' => $member]);
    }

    public function rejectMembership(MembershipApplication $application, Request $request): JsonResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $application = $this->applicationService->reject(
            $application,
            $request->user(),
            $request->input('reason'),
        );

        return response()->json(['data' => $application]);
    }
}
