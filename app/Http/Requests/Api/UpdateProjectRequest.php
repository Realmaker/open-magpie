<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Auth handled by middleware
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'status' => ['sometimes', 'string', 'in:active,paused,completed,archived'],
            'priority' => ['sometimes', 'string', 'in:low,medium,high,critical'],
            'category' => ['sometimes', 'string', 'max:255'],
            'repository_url' => ['sometimes', 'url', 'max:500'],
            'tech_stack' => ['sometimes', 'array'],
            'metadata' => ['sometimes', 'array'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'repository_url.url' => 'The repository URL must be a valid URL.',
            'tech_stack.array' => 'The tech stack must be an array.',
        ];
    }
}
