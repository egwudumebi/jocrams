<?php

namespace App\Services\Settings;

use App\Models\SystemSetting;
use App\Models\User;
use App\Support\Settings\SettingsCatalog;
use Illuminate\Support\Facades\Crypt;

class SettingsService
{
    /** @return list<array{key: string, value: mixed, is_secret: bool}> */
    public function getGroup(string $group): array
    {
        $this->assertValidGroup($group);

        return SystemSetting::query()
            ->where('group_name', $group)
            ->orderBy('setting_key')
            ->get()
            ->map(function (SystemSetting $setting): array {
                $value = (string) ($setting->setting_value ?? '');

                if ($setting->is_secret) {
                    return [
                        'key' => $setting->setting_key,
                        'value' => $value === '' ? null : '********',
                        'is_secret' => true,
                    ];
                }

                return [
                    'key' => $setting->setting_key,
                    'value' => $value,
                    'is_secret' => false,
                ];
            })
            ->all();
    }

    /** @param array<string, mixed> $values */
    public function saveGroup(string $group, array $values, User $actor): void
    {
        $this->assertValidGroup($group);

        $secretKeys = SettingsCatalog::secretKeys($group);
        $booleanKeys = SettingsCatalog::booleanKeys($group);

        foreach ($values as $key => $value) {
            $key = (string) $key;
            $isSecret = in_array($key, $secretKeys, true);
            $storedValue = $this->normalizeValue($value, in_array($key, $booleanKeys, true));

            if ($isSecret && $storedValue !== null && $storedValue !== '' && $storedValue !== '********') {
                $storedValue = Crypt::encryptString($storedValue);
            }

            $existing = SystemSetting::query()
                ->where('group_name', $group)
                ->where('setting_key', $key)
                ->first();

            if ($existing && ($storedValue === null || $storedValue === '' || $storedValue === '********')) {
                $storedValue = $existing->setting_value;
            }

            SystemSetting::query()->updateOrCreate(
                ['group_name' => $group, 'setting_key' => $key],
                [
                    'setting_value' => $storedValue,
                    'is_secret' => $isSecret,
                    'updated_by' => $actor->id,
                ],
            );
        }
    }

    private function assertValidGroup(string $group): void
    {
        abort_unless(in_array($group, SettingsCatalog::groups(), true), 404, 'Unknown settings group.');
    }

    private function normalizeValue(mixed $value, bool $isBoolean): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($isBoolean) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
        }

        return (string) $value;
    }
}
