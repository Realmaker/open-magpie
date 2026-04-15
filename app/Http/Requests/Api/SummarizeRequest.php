<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SummarizeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'min:10', 'max:100000'],
            'max_length' => ['nullable', 'integer', 'min:50', 'max:1000'],
            'language' => ['nullable', 'string', 'in:de,en'],
        ];
    }

    public function messages(): array
    {
        return [
            'text.required' => 'The text to summarize is required.',
            'text.min' => 'The text must be at least 10 characters long.',
        ];
    }
}
