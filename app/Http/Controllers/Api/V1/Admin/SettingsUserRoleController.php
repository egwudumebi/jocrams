<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AddUserRoleRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SettingsUserRoleController extends Controller
{
    public function store(AddUserRoleRequest $request): JsonResponse
    {
        $role = Role::query()->where('slug', $request->validated('role_slug'))->firstOrFail();
        $temporaryPassword = 'TempPass#'.random_int(10000, 99999);

        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Hash::make($temporaryPassword),
            'status' => UserStatus::Active,
            'has_changed_password' => false,
        ]);

        $user->assignRole($role);

        return response()->json([
            'message' => 'User role created.',
            'user_id' => $user->uuid,
            'temporary_password' => $temporaryPassword,
        ], 201);
    }
}
