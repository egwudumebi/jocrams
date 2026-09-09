<?php

namespace App\Http\Requests\Admin;

use App\Enums\PaymentPurpose;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordManualPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'member_uuid' => ['required', 'uuid', 'exists:members,uuid'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'purpose' => ['required', Rule::enum(PaymentPurpose::class)],
            'payment_method' => ['required', 'string', 'max:50'],
            'transaction_reference' => ['required', 'string', 'max:100', 'unique:payments,reference'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
