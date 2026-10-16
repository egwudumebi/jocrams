<?php

namespace App\Http\Controllers\Api\V1\Journal;

use App\Http\Controllers\Controller;
use App\Mail\ReviewerInvitationMail;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CreateReviewerController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc,dns', 'max:255'],
        ]);

        $reviewerRole = Role::query()->where('slug', 'reviewer')->first();
        if (! $reviewerRole) {
            return response()->json(['message' => 'Reviewer role not found'], 422);
        }

        $emailLower = strtolower($data['email']);
        $existing = User::query()->whereRaw('LOWER(email) = ?', [$emailLower])->first();

        if ($existing) {
            $alreadyReviewer = $existing->hasRole('reviewer');

            if ($alreadyReviewer) {
                return response()->json([
                    'message' => 'This user already has the reviewer role.',
                    'reviewer_id' => (string) $existing->uuid,
                ]);
            }

            $existing->assignRole($reviewerRole);

            Mail::to($existing->email)->send(new ReviewerInvitationMail(
                recipientName: $existing->name,
                recipientEmail: $existing->email,
            ));

            return response()->json([
                'message' => 'Reviewer role assigned to existing user. Invitation email sent.',
                'reviewer_id' => (string) $existing->uuid,
            ]);
        }

        $tempPassword = 'TempPass#'.random_int(10000, 99999);

        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($tempPassword),
            'status' => UserStatus::Active,
        ]);

        $user->assignRole($reviewerRole);

        Mail::to($user->email)->send(new ReviewerInvitationMail(
            recipientName: $user->name,
            recipientEmail: $user->email,
            temporaryPassword: $tempPassword,
        ));

        return response()->json([
            'message' => 'Reviewer created. Invitation email sent.',
            'reviewer_id' => (string) $user->uuid,
            'temporary_password' => $tempPassword,
        ], 201);
    }
}
