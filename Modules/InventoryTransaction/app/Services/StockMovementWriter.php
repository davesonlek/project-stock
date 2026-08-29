<?php

namespace Modules\InventoryTransaction\Services;

use Modules\InventoryTransaction\Enums\StockMovementType;
use Modules\InventoryTransaction\Exceptions\StockDocumentException;
use Modules\InventoryTransaction\Models\StockMovement;

class StockMovementWriter
{
    /**
     * Strictly insert an immutable movement record into the ledger.
     */
    public function write(
        string $organizationId,
        string $documentId,
        ?int $documentLineId,
        int $goodsId,
        int $warehouseId,
        int $locationId,
        StockMovementType|string $movementType,
        string|float $quantityDelta,
        string $performedBy,
        ?int $lotId = null,
        ?int $reversalOf = null
    ): ?StockMovement {
        $deltaStr = (string) $quantityDelta;

        // Non-zero delta rule
        if (bccomp($deltaStr, '0', 4) === 0) {
            return null;
        }

        $typeEnum = $movementType instanceof StockMovementType
            ? $movementType
            : StockMovementType::from(strtoupper($movementType));

        return StockMovement::create([
            'organization_id' => $organizationId,
            'document_id' => $documentId,
            'document_line_id' => $documentLineId,
            'goods_id' => $goodsId,
            'lot_id' => $lotId,
            'warehouse_id' => $warehouseId,
            'location_id' => $locationId,
            'movement_type' => $typeEnum,
            'quantity_delta' => $deltaStr,
            'reversal_of' => $reversalOf,
            'performed_by' => $performedBy,
            'created_at' => now(),
        ]);
    }
}
