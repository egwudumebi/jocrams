<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\MemberStatus;
use App\Http\Controllers\Controller;
use App\Services\Admin\BatchOperationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BatchController extends Controller
{
    public function __construct(private readonly BatchOperationService $batchService) {}

    public function approveApplications(Request $request): JsonResponse
    {
        $request->validate([
            'application_uuids' => ['required', 'array', 'min:1'],
            'application_uuids.*' => ['required', 'string', 'uuid'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $results = $this->batchService->batchApproveApplications(
            $request->input('application_uuids'),
            $request->user(),
            $request->input('notes'),
        );

        return response()->json(['data' => $results]);
    }

    public function rejectApplications(Request $request): JsonResponse
    {
        $request->validate([
            'application_uuids' => ['required', 'array', 'min:1'],
            'application_uuids.*' => ['required', 'string', 'uuid'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $results = $this->batchService->batchRejectApplications(
            $request->input('application_uuids'),
            $request->user(),
            $request->input('reason'),
        );

        return response()->json(['data' => $results]);
    }

    public function updateMemberStatus(Request $request): JsonResponse
    {
        $request->validate([
            'member_uuids' => ['required', 'array', 'min:1'],
            'member_uuids.*' => ['required', 'string', 'uuid'],
            'status' => ['required', 'string', 'in:active,suspended,expired'],
        ]);

        $results = $this->batchService->batchUpdateMemberStatus(
            $request->input('member_uuids'),
            $request->user(),
            MemberStatus::from($request->input('status')),
        );

        return response()->json(['data' => $results]);
    }
}
