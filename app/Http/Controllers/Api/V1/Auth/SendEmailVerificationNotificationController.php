<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SendEmailVerificationNotificationController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }

        $context = $user->isAdmin() ? 'admin' : 'member';
        $user->sendEmailVerificationNotification($context);

        return response()->json(['message' => 'Verification email sent.']);
    }
}
