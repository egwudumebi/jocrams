<?php

namespace App\Http\Requests\Membership;

use Illuminate\Foundation\Http\FormRequest;

class CreateApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'membership_tier_id' => ['required', 'exists:membership_tiers,id'],
            'form_data' => ['nullable', 'array'],
        ];
    }
}
