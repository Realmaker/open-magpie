<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
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
            'slug' => $this->slug,
            'category' => $this->category,
            'filename' => $this->filename,
            'current_version' => $this->current_version,
            'source' => $this->source,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Conditionally include current version content
            'content' => $this->when(
                $request->boolean('include_content') || $request->routeIs('*.show'),
                function () {
                    return $this->currentVersion?->content;
                }
            ),

            // Optional relationships
            'created_by' => new UserResource($this->whenLoaded('createdBy')),
            'project' => new ProjectResource($this->whenLoaded('project')),
            'current_version_data' => new DocumentVersionResource($this->whenLoaded('currentVersion')),
            'versions' => DocumentVersionResource::collection($this->whenLoaded('versions')),
        ];
    }
}
