<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\Auth\AuthService;
use App\Services\Membership\MembershipRenewalService;
use App\Support\Auth\UserProfilePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->authService->registerMember($request->validated());
        $login = $this->authService->loginMember($request->input('email'), $request->input('password'));
        $user = $login['user']->load('member.tier', 'roles.permissions', 'profile');

        return response()->json([
            'user' => $this->presentUser($user),
            'token' => $login['token'],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->loginMember(
            $request->input('email'),
            $request->input('password'),
        );

        $user = $result['user']->load('member.tier', 'roles.permissions', 'profile');

        return response()->json([
            'user' => $this->presentUser($user),
            'token' => $result['token'],
            'permissions' => $user->permissionSlugs(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user(), 'member-token');

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('member.tier', 'member.credentials', 'roles.permissions', 'profile');

        return response()->json([
            'user' => $this->presentUser($user),
            'permissions' => $user->permissionSlugs(),
        ]);
    }

    /** @return array<string, mixed> */
    private function presentUser(\App\Models\User $user): array
    {
        $payload = $user->toArray();
        $payload['profile'] = UserProfilePresenter::forUser($user);

        if ($user->member) {
            $payload['member']['renewal'] = app(MembershipRenewalService::class)->present($user->member);
        }

        return $payload;
    }
}
