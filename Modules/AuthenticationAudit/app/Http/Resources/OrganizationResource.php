<?php

namespace Modules\AuthenticationAudit\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $role = $this->pivot?->role_id ? $this->getRoleData() : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'industry' => $this->industry,
            'address' => $this->address,
            'role' => $role,
        ];
    }

    private function getRoleData(): ?array
    {
        if ($this->relationLoaded('users')) {
            $user = $this->users->first();
            if ($user && $user->pivot && $user->pivot->relationLoaded('role')) {
                return [
                    'code' => $user->pivot->role?->code,
                    'name' => $user->pivot->role?->name,
                ];
            }
        }

        // Fallback: If role model was directly attached or can be fetched via role_id
        if (isset($this->pivot->role_id)) {
            $role = \Modules\AuthenticationAudit\Models\Role::find($this->pivot->role_id);
            if ($role) {
                return [
                    'code' => $role->code,
                    'name' => $role->name,
                ];
            }
        }

        return null;
    }
}
