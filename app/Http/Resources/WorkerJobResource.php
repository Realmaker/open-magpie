<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkerJobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'prompt' => $this->prompt,
            'type' => $this->type,
            'status' => $this->status,
            'priority' => $this->priority,
            'project_path' => $this->project_path,
            'working_directory' => $this->working_directory,
            'environment' => $this->environment,
            'output' => $this->output,
            'error_output' => $this->error_output,
            'exit_code' => $this->exit_code,
            'duration_seconds' => $this->duration_seconds,
            'result_summary' => $this->result_summary,
            'approved_at' => $this->approved_at,
            'claimed_at' => $this->claimed_at,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Relationships
            'project' => new ProjectResource($this->whenLoaded('project')),
            'creator' => new UserResource($this->whenLoaded('creator')),
            'approver' => new UserResource($this->whenLoaded('approver')),
            'worker' => new WorkerResource($this->whenLoaded('worker')),
        ];
    }
}
