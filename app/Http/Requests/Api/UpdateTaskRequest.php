<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
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
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'status' => ['sometimes', 'string', 'in:open,in_progress,done,deferred,cancelled'],
            'priority' => ['sometimes', 'string', 'in:low,medium,high,critical'],
            'type' => ['sometimes', 'string', 'in:task,bug,feature,improvement,research,todo'],
            'source' => ['sometimes', 'string', 'in:claude-code,manual,api'],
            'labels' => ['sometimes', 'array'],
            'due_date' => ['sometimes', 'date', 'nullable'],
            'assigned_to' => ['sometimes', 'integer', 'nullable', 'exists:users,id'],
            'parent_id' => ['sometimes', 'integer', 'nullable', 'exists:tasks,id'],
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
            'assigned_to.exists' => 'The selected user does not exist.',
            'parent_id.exists' => 'The selected parent task does not exist.',
            'due_date.date' => 'The due date must be a valid date.',
        ];
    }
}
