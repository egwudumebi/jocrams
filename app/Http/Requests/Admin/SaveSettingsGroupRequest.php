<?php

namespace App\Http\Requests\Admin;

use App\Support\Settings\SettingsCatalog;
use Illuminate\Foundation\Http\FormRequest;

class SaveSettingsGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'values' => ['required', 'array'],
        ];
    }

    public function groupName(): string
    {
        return (string) $this->route('group');
    }

    protected function prepareForValidation(): void
    {
        abort_unless(in_array($this->groupName(), SettingsCatalog::groups(), true), 404);
    }
}
