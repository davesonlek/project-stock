<?php

namespace Modules\InventoryTransaction\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Traits\BelongsToOrganization;

class StockLot extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $table = 'stock_lots';
    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'goods_id',
        'lot_no',
        'manufactured_at',
        'expired_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'manufactured_at' => 'date',
            'expired_at' => 'date',
            'created_at' => 'datetime',
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

    public function lotBalances(): HasMany
    {
        return $this->hasMany(StockLotBalance::class, 'lot_id');
    }

    public function serialNumbers(): HasMany
    {
        return $this->hasMany(SerialNumber::class, 'lot_id');
    }
}
