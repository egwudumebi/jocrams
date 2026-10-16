<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'membership_tier_id' => [
                'required',
                Rule::exists('membership_tiers', 'id')->where('is_active', true),
            ],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'organization' => ['nullable', 'string', 'in:sicama'],
            'card_role' => ['nullable', 'string', 'max:80'],
            'send_password_reset' => ['sometimes', 'boolean'],
        ];
    }
}
