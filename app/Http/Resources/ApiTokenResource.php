<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiTokenResource extends JsonResource
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
            'last_four' => substr($this->token, -4),
            'last_used_at' => $this->last_used_at,
            'expires_at' => $this->expires_at,
            'abilities' => $this->abilities,
            'created_at' => $this->created_at,

            // Only include full token when explicitly requested (e.g., right after creation)
            'token' => $this->when($this->plainTextToken ?? false, $this->plainTextToken),

            // Optional relationships
            'user' => new UserResource($this->whenLoaded('user')),
            'team' => new TeamResource($this->whenLoaded('team')),
        ];
    }
}
