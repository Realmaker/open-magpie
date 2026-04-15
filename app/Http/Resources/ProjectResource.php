<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
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
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'category' => $this->category,
            'repository_url' => $this->repository_url,
            'tech_stack' => $this->tech_stack,
            'metadata' => $this->metadata,
            'health_score' => $this->health_score,
            'last_activity_at' => $this->last_activity_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Counts
            'events_count' => $this->whenCounted('events'),
            'documents_count' => $this->whenCounted('documents'),
            'tasks_count' => $this->whenCounted('tasks'),
            'open_tasks_count' => $this->whenLoaded('tasks', function () {
                return $this->tasks->where('status', 'open')->count();
            }),

            // Optional relationships
            'created_by' => new UserResource($this->whenLoaded('createdBy')),
            'team' => new TeamResource($this->whenLoaded('team')),
        ];
    }
}
