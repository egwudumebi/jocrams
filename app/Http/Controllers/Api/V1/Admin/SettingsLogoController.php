<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UploadSiteLogoRequest;
use App\Models\SystemSetting;
use App\Support\Settings\SiteBranding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SettingsLogoController extends Controller
{
    public function store(UploadSiteLogoRequest $request): JsonResponse
    {
        $file = $request->file('logo');
        $extension = $file->getClientOriginalExtension();
        $filename = 'site-logo-'.Str::uuid().'.'.$extension;
        $path = 'branding/'.$filename;

        Storage::disk('public')->putFileAs('branding', $file, $filename);

        $previousPath = SystemSetting::query()
            ->where('group_name', 'general')
            ->where('setting_key', 'site_logo_path')
            ->value('setting_value');

        if (is_string($previousPath) && $previousPath !== '' && $previousPath !== $path) {
            Storage::disk('public')->delete($previousPath);
        }

        SystemSetting::query()->updateOrCreate(
            ['group_name' => 'general', 'setting_key' => 'site_logo_path'],
            [
                'setting_value' => $path,
                'is_secret' => false,
                'updated_by' => $request->user()->id,
            ],
        );

        SiteBranding::clearCache();

        return response()->json([
            'message' => 'Site logo uploaded.',
            'path' => $path,
            'url' => SiteBranding::emailLogoUrl(),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $path = SystemSetting::query()
            ->where('group_name', 'general')
            ->where('setting_key', 'site_logo_path')
            ->value('setting_value');

        if (is_string($path) && $path !== '') {
            Storage::disk('public')->delete($path);
        }

        SystemSetting::query()->updateOrCreate(
            ['group_name' => 'general', 'setting_key' => 'site_logo_path'],
            [
                'setting_value' => '',
                'is_secret' => false,
                'updated_by' => $request->user()->id,
            ],
        );

        SiteBranding::clearCache();

        return response()->json(['message' => 'Site logo removed.']);
    }
}
