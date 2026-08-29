<?php

namespace Modules\AuthenticationAudit\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'username' => $this->username,
            'is_active' => $this->is_active,
            'is_verified' => $this->is_verified,
            'last_active_at' => $this->last_active_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
