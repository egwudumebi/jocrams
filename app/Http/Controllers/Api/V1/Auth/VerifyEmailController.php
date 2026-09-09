<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    public function __invoke(EmailVerificationRequest $request): JsonResponse|RedirectResponse
    {
        $request->fulfill();

        if ($request->expectsJson() || $request->boolean('json')) {
            return response()->json(['message' => 'Email verified.']);
        }

        $redirect = $request->user()->isAdmin()
            ? config('auth.verification_urls.admin_success', '/admin/dashboard')
            : config('auth.verification_urls.member_success', '/member/dashboard');

        return redirect($redirect);
    }
}
