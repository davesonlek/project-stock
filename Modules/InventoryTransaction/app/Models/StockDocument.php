<?php

namespace Modules\InventoryTransaction\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\AuthenticationAudit\Models\User;
use Modules\InventoryTransaction\Enums\StockDocumentStatus;
use Modules\InventoryTransaction\Enums\StockDocumentType;
use Modules\MasterData\Models\Supplier;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;
use Modules\MasterData\Traits\BelongsToOrganization;

class StockDocument extends Model
{
    use HasFactory, HasUuids, BelongsToOrganization;

    protected $table = 'stock_documents';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'organization_id',
        'document_no',
        'document_type',
        'status',
        'source_warehouse_id',
        'source_location_id',
        'destination_warehouse_id',
        'destination_location_id',
        'supplier_id',
        'created_by',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'cancelled_by',
        'cancelled_at',
        'cancel_reason',
        'posted_by',
        'posted_at',
        'reversal_of',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => StockDocumentType::class,
            'status' => StockDocumentStatus::class,
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'posted_at' => 'datetime',
        ];
    }

    public function getDocumentNumberAttribute(): ?string
    {
        return $this->document_no;
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'source_location_id');
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'destination_location_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function reversalDocument(): BelongsTo
    {
        return $this->belongsTo(StockDocument::class, 'reversal_of');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockDocumentLine::class, 'document_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'document_id');
    }
}
