<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NoteResource extends JsonResource
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
            'user_id' => $this->user_id,
            'notable_type' => $this->notable_type,
            'notable_id' => $this->notable_id,
            'parent_id' => $this->parent_id,
            'content' => $this->content,
            'ai_summary' => $this->ai_summary,
            'source' => $this->source,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Optional relationships
            'user' => new UserResource($this->whenLoaded('user')),
            'parent' => new NoteResource($this->whenLoaded('parent')),
            'replies' => NoteResource::collection($this->whenLoaded('replies')),
            'replies_count' => $this->whenCounted('replies'),
        ];
    }
}
