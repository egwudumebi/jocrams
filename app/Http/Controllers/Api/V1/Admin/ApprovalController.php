<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Approval;
use App\Models\MembershipApplication;
use App\Services\Membership\MembershipApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    public function __construct(private readonly MembershipApplicationService $applicationService) {}

    public function index(Request $request): JsonResponse
    {
        $approvals = Approval::query()
            ->where('status', 'pending')
            ->with(['approvable', 'submitter'])
            ->latest()
            ->paginate(20);

        return response()->json($approvals);
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
