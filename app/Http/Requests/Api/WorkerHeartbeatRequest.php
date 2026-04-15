<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class WorkerHeartbeatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'machine_id' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:online,offline,busy'],
            'version' => ['nullable', 'string', 'max:50'],
            'os_info' => ['nullable', 'string', 'max:255'],
            'capabilities' => ['nullable', 'array'],
            'current_jobs' => ['nullable', 'array'],
            'max_parallel_jobs' => ['nullable', 'integer', 'min:1', 'max:10'],
        ];
    }
}
