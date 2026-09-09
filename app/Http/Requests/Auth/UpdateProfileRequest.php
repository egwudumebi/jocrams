<?php

namespace App\Http\Requests\Auth;

use App\Support\Auth\Orcid;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('orcid')) {
            $this->merge([
                'orcid' => Orcid::normalize($this->input('orcid')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'position' => ['nullable', 'string', 'max:150'],
            'professional_bio' => ['nullable', 'string', 'max:5000'],
            'orcid' => ['nullable', 'string', 'max:19', 'regex:/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/i'],
            'country' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'street' => ['nullable', 'string', 'max:255'],
            'social_links' => ['nullable', 'array'],
            'social_links.*' => ['string', 'max:500'],
            'achievements' => ['nullable', 'array'],
            'achievements.*' => ['string', 'max:500'],
        ];
    }
}
