<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\UserProfile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProfileImageController extends Controller
{
    public function __invoke(string $userUuid)
    {
        $profile = UserProfile::query()->where('user_id', $userUuid)->first();

        if (! $profile?->profile_image_path) {
            throw new NotFoundHttpException('Profile image not found.');
        }

        $path = (string) $profile->profile_image_path;

        if (! Storage::disk('public')->exists($path)) {
            throw new NotFoundHttpException('Stored profile image is unavailable.');
        }

        return response()->file(Storage::disk('public')->path($path));
    }
}
