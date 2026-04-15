<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:open,in_progress,done,deferred,cancelled'],
            'priority' => ['nullable', 'string', 'in:low,medium,high,critical'],
            'type' => ['nullable', 'string', 'in:task,bug,feature,improvement,research,todo'],
            'source' => ['nullable', 'string', 'in:claude-code,manual,api'],
            'labels' => ['nullable', 'array'],
            'due_date' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'parent_id' => ['nullable', 'integer', 'exists:tasks,id'],
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
            'title.required' => 'The task title is required.',
            'assigned_to.exists' => 'The selected user does not exist.',
            'parent_id.exists' => 'The selected parent task does not exist.',
            'due_date.date' => 'The due date must be a valid date.',
        ];
    }
}
