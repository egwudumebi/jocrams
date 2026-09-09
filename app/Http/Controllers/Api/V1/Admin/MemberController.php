<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMemberRequest;
use App\Models\Member;
use App\Services\Admin\MemberQueryService;
use App\Services\Membership\MemberManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function __construct(
        private readonly MemberQueryService $memberQuery,
        private readonly MemberManagementService $memberManagement,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $members = $this->memberQuery->paginate($request->only([
            'status', 'membership_tier_id', 'search',
            'expires_before', 'expires_after', 'expiring_within_days',
            'joined_from', 'joined_to',
        ]));

        return response()->json($members);
    }

    public function show(Member $member): JsonResponse
    {
        return response()->json(['data' => $member->load(['user', 'tier', 'subscriptions', 'credentials'])]);
    }

    public function store(StoreMemberRequest $request): JsonResponse
    {
        $result = $this->memberManagement->addMember(
            $request->validated(),
            $request->user(),
        );

        return response()->json([
            'data' => $result['member'],
            'password_reset_sent' => $result['password_reset_sent'],
        ], 201);
    }

    public function deactivate(Member $member, Request $request): JsonResponse
    {
        $member = $this->memberManagement->deactivate($member, $request->user());

        return response()->json(['data' => $member]);
    }

    public function reactivate(Member $member, Request $request): JsonResponse
    {
        $member = $this->memberManagement->reactivate($member, $request->user());

        return response()->json(['data' => $member]);
    }
}
