<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ExtractTasksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'min:10'],
            'project_slug' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'text.required' => 'The text to extract tasks from is required.',
            'text.min' => 'The text must be at least 10 characters long.',
            'project_slug.required' => 'The project slug is required.',
        ];
    }
}
