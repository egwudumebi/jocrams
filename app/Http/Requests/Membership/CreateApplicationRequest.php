<?php

namespace App\Http\Requests\Membership;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'membership_tier_id' => [
                'required',
                Rule::exists('membership_tiers', 'id')->where('is_active', true),
            ],
            'form_data' => ['nullable', 'array'],
        ];
    }
}
