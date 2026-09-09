<?php

namespace App\Http\Requests\Events;

use App\Enums\EventMemberType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterPublicEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'member_type' => ['nullable', Rule::in(EventMemberType::values())],
            'membership_number' => [
                Rule::requiredIf(fn () => $this->input('member_type') === EventMemberType::Member->value),
                'nullable',
                'string',
                'max:32',
            ],
        ];
    }
}
