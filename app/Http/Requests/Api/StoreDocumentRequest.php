<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
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
            'slug' => ['nullable', 'string', 'max:255'],
            'category' => [
                'nullable',
                'string',
                'in:documentation,specification,changelog,readme,architecture,meeting_notes,guide,other'
            ],
            'content' => ['required', 'string', 'max:1000000'],
            'source' => ['nullable', 'string', 'in:claude-code,manual,api'],
            'change_note' => ['nullable', 'string', 'max:500'],
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
            'title.required' => 'The document title is required.',
            'content.required' => 'The document content is required.',
            'category.in' => 'The document category must be one of: documentation, specification, changelog, readme, architecture, meeting_notes, guide, other.',
            'change_note.max' => 'The change note cannot exceed 500 characters.',
        ];
    }
}
