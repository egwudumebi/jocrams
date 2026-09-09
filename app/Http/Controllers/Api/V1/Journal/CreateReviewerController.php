<?php

namespace App\Http\Controllers\Api\V1\Journal;

use App\Http\Controllers\Controller;
use App\Mail\JournalNotificationMail;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

            Mail::to($data['email'])->send(new JournalNotificationMail(
                'You have been added as a Journal Reviewer',
                "Hello {$existing->name},\n\nAn admin has granted you the journal reviewer role.\n\nLog in with your existing account using: {$data['email']}\n",
            ));

            return response()->json([
                'message' => 'Reviewer role assigned to existing user.',
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

        Mail::to($data['email'])->send(new JournalNotificationMail(
            'You have been added as a Journal Reviewer',
            "Hello {$data['name']},\n\nAn admin has added you as a journal reviewer.\n\nLogin email: {$data['email']}\nTemporary password: {$tempPassword}\n\nPlease log in and change your password immediately.\n",
        ));

        return response()->json([
            'message' => 'Reviewer created',
            'reviewer_id' => (string) $user->uuid,
            'temporary_password' => $tempPassword,
        ], 201);
    }
}
