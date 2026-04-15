<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class BulkStoreTaskRequest extends FormRequest
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
            'tasks' => ['required', 'array', 'min:1'],
            'tasks.*.title' => ['required', 'string', 'max:255'],
            'tasks.*.priority' => ['nullable', 'string', 'in:low,medium,high,critical'],
            'tasks.*.type' => ['nullable', 'string', 'in:task,bug,feature,improvement,research,todo'],
            'tasks.*.source' => ['nullable', 'string', 'in:claude-code,manual,api'],
            'tasks.*.description' => ['nullable', 'string'],
            'tasks.*.due_date' => ['nullable', 'date'],
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
            'tasks.required' => 'At least one task is required.',
            'tasks.array' => 'Tasks must be provided as an array.',
            'tasks.min' => 'At least one task must be provided.',
            'tasks.*.title.required' => 'Each task must have a title.',
            'tasks.*.title.max' => 'Task title cannot exceed 255 characters.',
            'tasks.*.due_date.date' => 'Due date must be a valid date.',
        ];
    }
}
