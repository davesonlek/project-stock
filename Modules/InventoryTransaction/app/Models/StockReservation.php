<?php

namespace Modules\InventoryTransaction\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\AuthenticationAudit\Models\User;
use Modules\InventoryTransaction\Enums\StockReservationStatus;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;

class StockReservation extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'stock_reservations';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'organization_id',
        'goods_id',
        'lot_id',
        'warehouse_id',
        'location_id',
        'document_id',
        'document_line_id',
        'quantity',
        'status',
        'expires_at',
        'created_by',
        'released_by',
        'released_at',
        'consumed_by',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'status' => StockReservationStatus::class,
            'expires_at' => 'datetime',
            'released_at' => 'datetime',
            'consumed_at' => 'datetime',
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

    public function document(): BelongsTo
    {
        return $this->belongsTo(StockDocument::class, 'document_id');
    }

    public function documentLine(): BelongsTo
    {
        return $this->belongsTo(StockDocumentLine::class, 'document_line_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function consumer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consumed_by');
    }
}
