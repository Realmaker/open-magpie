<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectShareResource extends JsonResource
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
            'shared_with_email' => $this->shared_with_email,
            'permission' => $this->permission,
            'accepted_at' => $this->accepted_at,
            'expires_at' => $this->expires_at,
            'is_pending' => $this->isPending(),
            'is_expired' => $this->isExpired(),
            'created_at' => $this->created_at,

            // Relationships
            'shared_by' => new UserResource($this->whenLoaded('sharedBy')),
            'shared_with_user' => new UserResource($this->whenLoaded('sharedWithUser')),
            'project' => new ProjectResource($this->whenLoaded('project')),
        ];
    }
}
