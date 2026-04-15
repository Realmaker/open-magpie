<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
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
            'type' => [
                'required',
                'string',
                'in:changelog,documentation,decision,milestone,note,task_update,session_summary,deployment,issue,review'
            ],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:500000'],
            'source' => ['nullable', 'string', 'in:claude-code,manual,api,system'],
            'source_session_id' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
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
            'type.required' => 'The event type is required.',
            'type.in' => 'The event type must be one of: changelog, documentation, decision, milestone, note, task_update, session_summary, deployment, issue, review.',
            'title.required' => 'The event title is required.',
            'content.required' => 'The event content is required.',
        ];
    }
}
