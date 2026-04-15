<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'notable_type' => ['required', 'string', 'in:project,task,document,event'],
            'notable_id' => ['required', 'integer'],
            'content' => ['required', 'string', 'max:65535'],
            'parent_id' => ['nullable', 'integer', 'exists:notes,id'],
            'source' => ['nullable', 'string', 'in:manual,voice,ai'],
        ];
    }

    public function messages(): array
    {
        return [
            'notable_type.required' => 'The notable type is required.',
            'notable_type.in' => 'The notable type must be one of: project, task, document, event.',
            'notable_id.required' => 'The notable ID is required.',
            'content.required' => 'The note content is required.',
        ];
    }
}
