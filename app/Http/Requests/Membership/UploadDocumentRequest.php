<?php

namespace App\Http\Requests\Membership;

use Illuminate\Foundation\Http\FormRequest;

class UploadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'document' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
            'document_type' => ['required', 'string', 'max:50'],
        ];
    }
}
