<?php

namespace Modules\AuthenticationAudit\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurrentOrganizationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'organization' => [
                'id' => $this->organization()->id,
                'name' => $this->organization()->name,
                'industry' => $this->organization()->industry,
                'address' => $this->organization()->address,
            ],
            'role' => [
                'id' => $this->role()->id,
                'code' => $this->role()->code,
                'name' => $this->role()->name,
            ],
            'user' => [
                'id' => $this->user()->id,
                'email' => $this->user()->email,
                'username' => $this->user()->username,
            ],
        ];
    }
}
