<?php

namespace Modules\MasterData\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\MasterData\Traits\BelongsToOrganization;

class WarehouseLocation extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $table = 'warehouse_locations';

    protected $fillable = [
        'organization_id',
        'warehouse_id',
        'code',
        'name',
        'zone',
        'aisle',
        'rack',
        'bin',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}
