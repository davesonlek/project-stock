<?php

namespace Modules\MasterData\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\MasterData\Traits\BelongsToOrganization;

class GoodsSupplier extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $table = 'goods_suppliers';

    protected $fillable = [
        'organization_id',
        'supplier_id',
        'goods_id',
        'supplier_sku',
        'purchase_price',
        'lead_time_days',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:4',
            'lead_time_days' => 'integer',
            'is_primary' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function goods(): BelongsTo
    {
        return $this->belongsTo(Goods::class, 'goods_id');
    }
}
