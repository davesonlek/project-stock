<?php

namespace Modules\InventoryTransaction\Services;

use Illuminate\Support\Facades\DB;
use Modules\InventoryTransaction\Enums\StockDocumentType;

class StockDocumentNumberGenerator
{
    /**
     * Generate unique, concurrency-safe document number using PostgreSQL sequence.
     */
    public function generate(StockDocumentType|string $type): string
    {
        $typeEnum = is_string($type) ? StockDocumentType::from(strtoupper($type)) : $type;

        $prefix = match ($typeEnum) {
            StockDocumentType::RECEIVE => 'RCV',
            StockDocumentType::ISSUE => 'ISS',
            StockDocumentType::TRANSFER => 'TRF',
            StockDocumentType::ADJUSTMENT => 'ADJ',
            StockDocumentType::REVERSAL => 'REV',
        };

        $datePart = now()->format('Ymd');
        $seqResult = DB::select("SELECT nextval('stock_document_no_seq') AS seq");
        $seq = $seqResult[0]->seq ?? 1;

        return sprintf('%s-%s-%06d', $prefix, $datePart, $seq);
    }
}
