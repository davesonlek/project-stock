<?php

namespace Modules\InventoryTransaction\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockDocumentLineSerial extends Model
{
    use HasFactory;

    protected $table = 'stock_document_line_serials';
    public $timestamps = false;

    protected $fillable = [
        'document_id',
        'document_line_id',
        'serial_id',
        'serial_no',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(StockDocument::class, 'document_id');
    }

    public function documentLine(): BelongsTo
    {
        return $this->belongsTo(StockDocumentLine::class, 'document_line_id');
    }

    public function serial(): BelongsTo
    {
        return $this->belongsTo(SerialNumber::class, 'serial_id');
    }
}
