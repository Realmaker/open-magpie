<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkerJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'prompt' => ['sometimes', 'string'],
            'description' => ['nullable', 'string'],
            'type' => ['sometimes', 'string', 'in:code_change,new_project,prepared'],
            'priority' => ['sometimes', 'string', 'in:low,medium,high,critical'],
            'project_path' => ['nullable', 'string', 'max:500'],
            'working_directory' => ['nullable', 'string', 'max:500'],
            'environment' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
