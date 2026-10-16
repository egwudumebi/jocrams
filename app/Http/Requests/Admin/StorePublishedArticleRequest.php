<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePublishedArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:500'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('published_articles', 'slug')],
            'authors' => ['required'],
            'authors.*.name' => ['required', 'string', 'max:255'],
            'authors.*.affiliation' => ['nullable', 'string', 'max:500'],
            'authors.*.orcid' => ['nullable', 'string', 'max:50'],
            'abstract' => ['required', 'string'],
            'keywords' => ['nullable', 'string', 'max:1000'],
            'article_type' => ['nullable', 'string', 'max:120'],
            'received_at' => ['nullable', 'date'],
            'revised_at' => ['nullable', 'date'],
            'accepted_at' => ['nullable', 'date'],
            'published_at' => ['nullable', 'date'],
            'journal_issue_id' => ['nullable', 'integer', 'exists:journal_issues,id'],
            'volume_number' => ['nullable', 'integer', 'min:1'],
            'issue_number' => ['nullable', 'integer', 'min:1'],
            'page_start' => ['nullable', 'string', 'max:32'],
            'page_end' => ['nullable', 'string', 'max:32'],
            'citation' => ['nullable', 'string'],
            'copyright' => ['nullable', 'string', 'max:500'],
            'license' => ['nullable', 'string', 'max:500'],
            'doi' => ['nullable', 'string', 'max:255', Rule::unique('published_articles', 'doi')],
            'status' => ['nullable', Rule::in(['draft', 'published'])],
            'is_crossref_sample' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'pdf' => ['nullable', 'file', 'mimes:pdf', 'max:51200'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('authors') && is_string($this->input('authors'))) {
            $decoded = json_decode($this->input('authors'), true);
            if (is_array($decoded)) {
                $this->merge(['authors' => $decoded]);
            }
        }

        foreach (['is_crossref_sample'] as $boolField) {
            if ($this->has($boolField)) {
                $this->merge([$boolField => filter_var($this->input($boolField), FILTER_VALIDATE_BOOLEAN)]);
            }
        }
    }
}
