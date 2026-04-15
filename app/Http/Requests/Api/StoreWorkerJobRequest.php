<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkerJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'prompt' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'project_slug' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'in:code_change,new_project,prepared'],
            'priority' => ['nullable', 'string', 'in:low,medium,high,critical'],
            'project_path' => ['nullable', 'string', 'max:500'],
            'working_directory' => ['nullable', 'string', 'max:500'],
            'environment' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'A job title is required.',
            'prompt.required' => 'A prompt for Claude is required.',
        ];
    }
}
