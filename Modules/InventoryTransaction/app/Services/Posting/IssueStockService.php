<?php

namespace Modules\InventoryTransaction\Services\Posting;

use Illuminate\Support\Collection;
use Modules\InventoryTransaction\Enums\StockMovementType;
use Modules\InventoryTransaction\Exceptions\StockDocumentException;
use Modules\InventoryTransaction\Models\StockDocument;
use Modules\InventoryTransaction\Services\StockBalanceManager;
use Modules\InventoryTransaction\Services\StockLotManager;
use Modules\InventoryTransaction\Services\SerialNumberManager;
use Modules\InventoryTransaction\Services\StockMovementWriter;

class IssueStockService
{
    public function __construct(
        protected StockBalanceManager $balanceManager,
        protected StockLotManager $lotManager,
        protected SerialNumberManager $serialManager,
        protected StockMovementWriter $movementWriter
    ) {}

    public function post(
        string $orgId,
        string $userId,
        StockDocument $document,
        Collection $lockedBalances,
        Collection $lockedLotBalances,
        Collection $lockedSerials
    ): void {
        $srcWhId = $document->source_warehouse_id;
        $srcLocId = $document->source_location_id;

        foreach ($document->lines as $line) {
            $goods = $line->goods;
            $qty = (string) $line->quantity;

            // Check if this line has an ACTIVE reservation
            /** @var \Modules\InventoryTransaction\Models\StockReservation|null $activeReservation */
            $activeReservation = \Modules\InventoryTransaction\Models\StockReservation::where('document_line_id', $line->id)
                ->where('status', \Modules\InventoryTransaction\Enums\StockReservationStatus::ACTIVE)
                ->lockForUpdate()
                ->first();

            $balKey = "{$srcWhId}:{$srcLocId}:{$line->goods_id}";
            $balance = $lockedBalances->get($balKey);
            if (!$balance) {
                throw new StockDocumentException('Stock balance row not found under lock', 'STOCK_BALANCE_ERROR', 500);
            }

            if ($activeReservation) {
                // Validate reservation quantity matches line
                if (bccomp((string) $activeReservation->quantity, $qty, 4) !== 0) {
                    throw new StockDocumentException('Reservation quantity mismatch with line quantity', 'RESERVATION_BALANCE_MISMATCH', 409);
                }

                // 1. Consume Reserved Stock Balance
                $this->balanceManager->consumeReserved($balance, $qty);

                // 2. Consume Reserved Lot Balance
                if ($goods->is_lot_tracked) {
                    if (!$line->lot_id) {
                        throw new StockDocumentException('Lot ID is required for lot-tracked goods issue', 'LOT_REQUIRED', 422);
                    }
                    $lotKey = "{$srcWhId}:{$srcLocId}:{$line->lot_id}";
                    $lotBalance = $lockedLotBalances->get($lotKey);
                    if (!$lotBalance) {
                        throw new StockDocumentException('Stock lot balance row not found under lock', 'INSUFFICIENT_LOT_STOCK', 409);
                    }
                    $this->lotManager->consumeReserved($lotBalance, $qty);
                }

                // 3. Consume Reserved Serials
                if ($goods->is_serial_tracked) {
                    $serialNos = $line->lineSerials->pluck('serial_no')->toArray();
                    $this->serialManager->consumeReservedSerials(
                        lockedSerials: $lockedSerials,
                        serialNos: $serialNos,
                        goodsId: $goods->id
                    );
                }

                // 4. Mark Reservation as CONSUMED
                $activeReservation->update([
                    'status' => \Modules\InventoryTransaction\Enums\StockReservationStatus::CONSUMED,
                    'consumed_by' => $userId,
                    'consumed_at' => now(),
                ]);
            } else {
                // Standard Issue Deduction (when no reservation exists)
                $this->balanceManager->decrementOnHand($balance, $qty);

                if ($goods->is_lot_tracked) {
                    if (!$line->lot_id) {
                        throw new StockDocumentException('Lot ID is required for lot-tracked goods issue', 'LOT_REQUIRED', 422);
                    }

                    $lotKey = "{$srcWhId}:{$srcLocId}:{$line->lot_id}";
                    $lotBalance = $lockedLotBalances->get($lotKey);
                    if (!$lotBalance) {
                        throw new StockDocumentException('Stock lot balance row not found under lock', 'INSUFFICIENT_LOT_STOCK', 409);
                    }

                    $this->lotManager->decrementOnHand($lotBalance, $qty, checkExpiry: true);
                }

                if ($goods->is_serial_tracked) {
                    $serialNos = $line->lineSerials->pluck('serial_no')->toArray();
                    $this->serialManager->issueSerials(
                        lockedSerials: $lockedSerials,
                        serialNos: $serialNos,
                        goodsId: $goods->id,
                        sourceWarehouseId: $srcWhId,
                        sourceLocationId: $srcLocId
                    );
                }
            }

            // 4. Insert Movement Ledger (-quantity)
            $negQty = bcmul($qty, '-1', 4);
            $this->movementWriter->write(
                organizationId: $orgId,
                documentId: (string) $document->id,
                documentLineId: $line->id,
                goodsId: $goods->id,
                warehouseId: $srcWhId,
                locationId: $srcLocId,
                movementType: StockMovementType::ISSUE,
                quantityDelta: $negQty,
                performedBy: $userId,
                lotId: $line->lot_id
            );
        }
    }
}
