<?php

namespace App\Http\Requests\Admin;

use App\Enums\Visibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDownloadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('allowed_tier_ids') && is_string($this->input('allowed_tier_ids'))) {
            $decoded = json_decode($this->input('allowed_tier_ids'), true);
            if (is_array($decoded)) {
                $this->merge(['allowed_tier_ids' => $decoded]);
            }
        }

        if ($this->has('is_active')) {
            $this->merge(['is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $this->input('is_active')]);
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'visibility' => ['nullable', Rule::enum(Visibility::class)],
            'allowed_tier_ids' => ['required_if:visibility,tier_specific', 'array', 'min:1'],
            'allowed_tier_ids.*' => ['integer', 'exists:membership_tiers,id'],
            'is_active' => ['nullable', 'boolean'],
            'file' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:25600'],
        ];
    }
}
