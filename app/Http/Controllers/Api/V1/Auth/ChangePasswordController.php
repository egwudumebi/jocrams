<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use Illuminate\Support\Facades\Hash;

class ChangePasswordController extends Controller
{
    public function __invoke(ChangePasswordRequest $request)
    {
        $user = $request->user();

        if (strtolower((string) $user->email) !== strtolower((string) $request->string('email'))) {
            return response()->json(['message' => 'Email does not match authenticated user'], 422);
        }

        if (! Hash::check((string) $request->string('current_password'), (string) $user->password)) {
            return response()->json(['message' => 'Current password is incorrect'], 422);
        }

        $user->password = (string) $request->string('new_password');
        $user->has_changed_password = true;
        $user->save();

        return response()->json(['message' => 'Password updated']);
    }
}
