<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectSnapshotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'version' => $this->version,
            'file_size' => $this->file_size,
            'human_size' => $this->human_size,
            'file_count' => $this->file_count,
            'file_tree' => $this->when($request->boolean('include_tree', false), $this->file_tree),
            'change_note' => $this->change_note,
            'source' => $this->source,
            'created_at' => $this->created_at,
            'uploaded_by' => new UserResource($this->whenLoaded('uploader')),
        ];
    }
}
