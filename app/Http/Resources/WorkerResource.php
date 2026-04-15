<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'machine_id' => $this->machine_id,
            'status' => $this->status,
            'is_online' => $this->isOnline(),
            'version' => $this->version,
            'os_info' => $this->os_info,
            'capabilities' => $this->capabilities,
            'current_jobs' => $this->current_jobs,
            'max_parallel_jobs' => $this->max_parallel_jobs,
            'last_heartbeat_at' => $this->last_heartbeat_at,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
