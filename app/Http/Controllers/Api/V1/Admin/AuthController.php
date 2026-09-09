<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->loginAdmin(
            $request->input('email'),
            $request->input('password'),
        );

        return response()->json([
            'user' => $result['user']->load('roles.permissions'),
            'token' => $result['token'],
            'permissions' => $result['user']->permissionSlugs(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user(), 'admin-token');

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user()->load('roles.permissions'),
            'permissions' => $request->user()->permissionSlugs(),
        ]);
    }
}
