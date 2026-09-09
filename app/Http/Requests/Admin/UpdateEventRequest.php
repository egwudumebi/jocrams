<?php

namespace App\Http\Requests\Admin;

use App\Enums\EventMemberType;
use App\Enums\Visibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['sessions', 'pricing_tiers'] as $key) {
            if ($this->has($key) && is_string($this->input($key))) {
                $decoded = json_decode($this->input($key), true);
                if (is_array($decoded)) {
                    $this->merge([$key => $decoded]);
                }
            }
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'body' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'virtual_url' => ['nullable', 'url', 'max:500'],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['nullable', 'date'],
            'registration_opens_at' => ['nullable', 'date'],
            'registration_closes_at' => ['nullable', 'date'],
            'max_attendees' => ['nullable', 'integer', 'min:0'],
            'fee' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'visibility' => ['sometimes', Rule::enum(Visibility::class)],
            'status' => ['sometimes', 'in:draft,published'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'banner' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'sessions' => ['nullable', 'array'],
            'sessions.*.title' => ['required_with:sessions', 'string', 'max:255'],
            'sessions.*.starts_at' => ['required_with:sessions', 'date'],
            'sessions.*.ends_at' => ['nullable', 'date'],
            'sessions.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'pricing_tiers' => ['nullable', 'array'],
            'pricing_tiers.*.category' => ['required_with:pricing_tiers', Rule::in(EventMemberType::values())],
            'pricing_tiers.*.fee' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
