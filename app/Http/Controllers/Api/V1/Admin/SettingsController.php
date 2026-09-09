<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveSettingsGroupRequest;
use App\Services\Settings\SettingsService;
use App\Support\Settings\SiteBanner;
use App\Support\Settings\SettingsCatalog;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    public function __construct(private readonly SettingsService $settingsService) {}

    public function show(string $group): JsonResponse
    {
        return response()->json([
            'group' => $group,
            'data' => $this->settingsService->getGroup($group),
        ]);
    }

    public function update(SaveSettingsGroupRequest $request, string $group): JsonResponse
    {
        $this->settingsService->saveGroup(
            $group,
            $request->validated('values'),
            $request->user(),
        );

        if ($group === 'general') {
            SiteBanner::clearCache();
        }

        return response()->json(['message' => 'Settings saved.']);
    }

    public function groups(): JsonResponse
    {
        return response()->json(['data' => SettingsCatalog::groups()]);
    }
}
