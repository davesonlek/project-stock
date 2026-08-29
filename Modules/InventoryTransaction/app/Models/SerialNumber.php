<?php

namespace Modules\InventoryTransaction\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\InventoryTransaction\Enums\SerialNumberStatus;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;
use Modules\MasterData\Traits\BelongsToOrganization;

class SerialNumber extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $table = 'serial_numbers';

    protected $fillable = [
        'organization_id',
        'goods_id',
        'lot_id',
        'serial_no',
        'warehouse_id',
        'location_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => SerialNumberStatus::class,
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function goods(): BelongsTo
    {
        return $this->belongsTo(Goods::class, 'goods_id');
    }

    public function stockLot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class, 'lot_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'location_id');
    }
}
