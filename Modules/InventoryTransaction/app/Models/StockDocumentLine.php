<?php

namespace Modules\InventoryTransaction\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\MasterData\Models\Goods;

class StockDocumentLine extends Model
{
    use HasFactory;

    protected $table = 'stock_document_lines';

    protected $fillable = [
        'document_id',
        'goods_id',
        'lot_id',
        'quantity',
        'counted_quantity',
        'unit_cost',
        'lot_no',
        'manufactured_at',
        'expired_at',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'counted_quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'manufactured_at' => 'date',
            'expired_at' => 'date',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(StockDocument::class, 'document_id');
    }

    public function goods(): BelongsTo
    {
        return $this->belongsTo(Goods::class, 'goods_id');
    }

    public function stockLot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class, 'lot_id');
    }

    public function lineSerials(): HasMany
    {
        return $this->hasMany(StockDocumentLineSerial::class, 'document_line_id');
    }

    public function serials(): BelongsToMany
    {
        return $this->belongsToMany(SerialNumber::class, 'stock_document_line_serials', 'document_line_id', 'serial_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'document_line_id');
    }
}
