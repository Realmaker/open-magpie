<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CompleteWorkerJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:done,failed'],
            'output' => ['nullable', 'string', 'max:51200'],
            'error_output' => ['nullable', 'string', 'max:10240'],
            'exit_code' => ['nullable', 'integer'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'result_summary' => ['nullable', 'string'],
        ];
    }
}
