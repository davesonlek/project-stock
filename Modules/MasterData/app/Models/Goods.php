<?php

namespace Modules\MasterData\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\AuthenticationAudit\Models\User;
use Modules\InventoryTransaction\Models\SerialNumber;
use Modules\InventoryTransaction\Models\StockBalance;
use Modules\InventoryTransaction\Models\StockLot;
use Modules\MasterData\Traits\BelongsToOrganization;

class Goods extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $table = 'goods';

    protected $fillable = [
        'organization_id',
        'product_id',
        'unit_id',
        'barcode',
        'pack_size',
        'cost',
        'sell_price',
        'is_lot_tracked',
        'is_serial_tracked',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'pack_size' => 'decimal:4',
            'cost' => 'decimal:4',
            'sell_price' => 'decimal:4',
            'is_lot_tracked' => 'boolean',
            'is_serial_tracked' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function getNameAttribute(): ?string
    {
        return $this->product?->name;
    }

    public function getSkuAttribute(): ?string
    {
        return $this->product?->sku;
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function goodsSuppliers(): HasMany
    {
        return $this->hasMany(GoodsSupplier::class, 'goods_id');
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'goods_suppliers', 'goods_id', 'supplier_id')
            ->withPivot(['supplier_sku', 'purchase_price', 'lead_time_days', 'is_primary'])
            ->withTimestamps();
    }

    public function stockBalances(): HasMany
    {
        return $this->hasMany(StockBalance::class, 'goods_id');
    }

    public function stockLots(): HasMany
    {
        return $this->hasMany(StockLot::class, 'goods_id');
    }

    public function serialNumbers(): HasMany
    {
        return $this->hasMany(SerialNumber::class, 'goods_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
