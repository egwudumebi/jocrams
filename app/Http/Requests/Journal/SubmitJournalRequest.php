<?php

namespace App\Http\Requests\Journal;

use App\Support\Journal\JournalManuscriptRules;
use Illuminate\Foundation\Http\FormRequest;

class SubmitJournalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'author_name' => ['nullable', 'string', 'max:150'],
            'author_email' => ['nullable', 'email:rfc,dns', 'max:255'],
            'abstract' => ['nullable', 'string', 'max:5000'],
            'category' => ['nullable', 'string', 'max:120'],
            'keywords' => ['nullable', 'string', 'max:500'],
            'mins_read' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'references' => ['nullable', 'array'],
            'references.*' => ['string', 'max:2000'],
            'call_for_papers_uuid' => ['required', 'uuid'],
            'document' => JournalManuscriptRules::document(),
        ];
    }
}
