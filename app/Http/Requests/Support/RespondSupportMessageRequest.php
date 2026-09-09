<?php

namespace App\Http\Requests\Support;

use Illuminate\Foundation\Http\FormRequest;

class RespondSupportMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'response' => ['required', 'string', 'max:5000'],
        ];
    }
}
