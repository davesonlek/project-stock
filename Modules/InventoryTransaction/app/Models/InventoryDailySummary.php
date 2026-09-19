<?php

namespace Modules\InventoryTransaction\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;

class InventoryDailySummary extends Model
{
    protected $table = 'inventory_daily_summaries';

    protected $guarded = [
        'id',
        'organization_id',
    ];

    protected function casts(): array
    {
        return [
            'summary_date' => 'date',
            'opening_qty' => 'decimal:4',
            'receive_qty' => 'decimal:4',
            'issue_qty' => 'decimal:4',
            'transfer_in_qty' => 'decimal:4',
            'transfer_out_qty' => 'decimal:4',
            'adjust_in_qty' => 'decimal:4',
            'adjust_out_qty' => 'decimal:4',
            'reversal_net_qty' => 'decimal:4',
            'net_movement_qty' => 'decimal:4',
            'closing_qty' => 'decimal:4',
            'movement_count' => 'integer',
            'refreshed_at' => 'datetime',
        ];
    }

    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where($this->getTable().'.organization_id', $organizationId);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'location_id');
    }

    public function goods(): BelongsTo
    {
        return $this->belongsTo(Goods::class, 'goods_id');
    }
}
