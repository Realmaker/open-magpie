<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'type' => $this->type,
            'source' => $this->source,
            'labels' => $this->labels,
            'due_date' => $this->due_date,
            'completed_at' => $this->completed_at,
            'sort_order' => $this->sort_order,
            'metadata' => $this->metadata,
            'assigned_to' => $this->assigned_to,
            'parent_id' => $this->parent_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Computed fields
            'is_overdue' => $this->when(
                $this->due_date && $this->status !== 'done' && $this->status !== 'cancelled',
                function () {
                    return $this->due_date < now()->toDateString();
                }
            ),

            // Optional relationships
            'created_by' => new UserResource($this->whenLoaded('createdBy')),
            'assigned_user' => new UserResource($this->whenLoaded('assignedUser')),
            'project' => new ProjectResource($this->whenLoaded('project')),
            'parent_task' => new TaskResource($this->whenLoaded('parentTask')),
            'subtasks' => TaskResource::collection($this->whenLoaded('subtasks')),
            'subtasks_count' => $this->whenCounted('subtasks'),
        ];
    }
}
