<?php

namespace Modules\MasterData\Traits;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToOrganization
{
    /**
     * Scope query to a specific organization.
     */
    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where($this->getTable() . '.organization_id', $organizationId);
    }
}
