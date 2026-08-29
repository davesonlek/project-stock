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

class AdjustmentStockService
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
        Collection $lockedLotBalances
    ): void {
        $whId = $document->source_warehouse_id;
        $locId = $document->source_location_id;

        foreach ($document->lines as $line) {
            $goods = $line->goods;
            $countedQty = (string) $line->counted_quantity;

            $balKey = "{$whId}:{$locId}:{$line->goods_id}";
            $balance = $lockedBalances->get($balKey);
            if (!$balance) {
                throw new StockDocumentException('Stock balance row not found under lock', 'STOCK_BALANCE_ERROR', 500);
            }

            // --- 1. Serial Tracking Adjustment ---
            if ($goods->is_serial_tracked) {
                $countedSerials = $line->lineSerials->pluck('serial_no')->toArray();
                if ((int) $countedQty != $countedQty || count($countedSerials) !== (int) $countedQty) {
                    throw new StockDocumentException(
                        "Counted quantity must be integer and match serial count for adjustment",
                        'SERIAL_COUNT_MISMATCH',
                        422
                    );
                }

                $adjustResult = $this->serialManager->adjustSerials(
                    orgId: $orgId,
                    goodsId: $goods->id,
                    warehouseId: $whId,
                    locationId: $locId,
                    countedSerialNos: $countedSerials
                );

                $missingCount = count($adjustResult['missing']);
                $foundCount = count($adjustResult['found']);
                $delta = (string) ($foundCount - $missingCount);

                // Set balance to exact counted quantity
                $this->balanceManager->setOnHand($balance, $countedQty);

                if ($goods->is_lot_tracked && $line->lot_id) {
                    $lotKey = "{$whId}:{$locId}:{$line->lot_id}";
                    $lotBalance = $lockedLotBalances->get($lotKey);
                    if ($lotBalance) {
                        $this->lotManager->setOnHand($lotBalance, $countedQty);
                    }
                }

                // Write aggregate movement if delta != 0
                if (bccomp($delta, '0', 4) > 0) {
                    $this->movementWriter->write(
                        organizationId: $orgId,
                        documentId: (string) $document->id,
                        documentLineId: $line->id,
                        goodsId: $goods->id,
                        warehouseId: $whId,
                        locationId: $locId,
                        movementType: StockMovementType::ADJUST_IN,
                        quantityDelta: $delta,
                        performedBy: $userId,
                        lotId: $line->lot_id
                    );
                } elseif (bccomp($delta, '0', 4) < 0) {
                    $this->movementWriter->write(
                        organizationId: $orgId,
                        documentId: (string) $document->id,
                        documentLineId: $line->id,
                        goodsId: $goods->id,
                        warehouseId: $whId,
                        locationId: $locId,
                        movementType: StockMovementType::ADJUST_OUT,
                        quantityDelta: $delta,
                        performedBy: $userId,
                        lotId: $line->lot_id
                    );
                }

                continue;
            }

            // --- 2. Lot Tracking Adjustment ---
            if ($goods->is_lot_tracked) {
                if (!$line->lot_id) {
                    throw new StockDocumentException('Lot ID is required for lot adjustment', 'LOT_REQUIRED', 422);
                }

                $lotKey = "{$whId}:{$locId}:{$line->lot_id}";
                $lotBalance = $lockedLotBalances->get($lotKey);
                if (!$lotBalance) {
                    throw new StockDocumentException('Stock lot balance row not found under lock', 'STOCK_BALANCE_ERROR', 500);
                }

                $delta = bcsub($countedQty, (string) $lotBalance->on_hand, 4);

                // Set lot on_hand
                $this->lotManager->setOnHand($lotBalance, $countedQty);

                // Apply delta to main balance
                $newMainOnHand = bcadd((string) $balance->on_hand, $delta, 4);
                $this->balanceManager->setOnHand($balance, $newMainOnHand);

                // Movement based on delta
                if (bccomp($delta, '0', 4) > 0) {
                    $this->movementWriter->write(
                        organizationId: $orgId,
                        documentId: (string) $document->id,
                        documentLineId: $line->id,
                        goodsId: $goods->id,
                        warehouseId: $whId,
                        locationId: $locId,
                        movementType: StockMovementType::ADJUST_IN,
                        quantityDelta: $delta,
                        performedBy: $userId,
                        lotId: $line->lot_id
                    );
                } elseif (bccomp($delta, '0', 4) < 0) {
                    $this->movementWriter->write(
                        organizationId: $orgId,
                        documentId: (string) $document->id,
                        documentLineId: $line->id,
                        goodsId: $goods->id,
                        warehouseId: $whId,
                        locationId: $locId,
                        movementType: StockMovementType::ADJUST_OUT,
                        quantityDelta: $delta,
                        performedBy: $userId,
                        lotId: $line->lot_id
                    );
                }

                continue;
            }

            // --- 3. Standard Non-Lot, Non-Serial Adjustment ---
            $delta = bcsub($countedQty, (string) $balance->on_hand, 4);
            $this->balanceManager->setOnHand($balance, $countedQty);

            if (bccomp($delta, '0', 4) > 0) {
                $this->movementWriter->write(
                    organizationId: $orgId,
                    documentId: (string) $document->id,
                    documentLineId: $line->id,
                    goodsId: $goods->id,
                    warehouseId: $whId,
                    locationId: $locId,
                    movementType: StockMovementType::ADJUST_IN,
                    quantityDelta: $delta,
                    performedBy: $userId
                );
            } elseif (bccomp($delta, '0', 4) < 0) {
                $this->movementWriter->write(
                    organizationId: $orgId,
                    documentId: (string) $document->id,
                    documentLineId: $line->id,
                    goodsId: $goods->id,
                    warehouseId: $whId,
                    locationId: $locId,
                    movementType: StockMovementType::ADJUST_OUT,
                    quantityDelta: $delta,
                    performedBy: $userId
                );
            }
        }
    }
}
