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

class TransferStockService
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
        $destWhId = $document->destination_warehouse_id;
        $destLocId = $document->destination_location_id;

        foreach ($document->lines as $line) {
            $goods = $line->goods;
            $qty = (string) $line->quantity;

            // 1. Mutate Balances
            $srcBalKey = "{$srcWhId}:{$srcLocId}:{$line->goods_id}";
            $destBalKey = "{$destWhId}:{$destLocId}:{$line->goods_id}";

            $srcBalance = $lockedBalances->get($srcBalKey);
            $destBalance = $lockedBalances->get($destBalKey);

            if (!$srcBalance || !$destBalance) {
                throw new StockDocumentException('Stock balance row not found under lock', 'STOCK_BALANCE_ERROR', 500);
            }

            // Deduct source and increment destination
            $this->balanceManager->decrementOnHand($srcBalance, $qty);
            $this->balanceManager->incrementOnHand($destBalance, $qty);

            // 2. Mutate Lot Balances
            if ($goods->is_lot_tracked) {
                if (!$line->lot_id) {
                    throw new StockDocumentException('Lot ID is required for lot-tracked goods transfer', 'LOT_REQUIRED', 422);
                }

                $srcLotKey = "{$srcWhId}:{$srcLocId}:{$line->lot_id}";
                $destLotKey = "{$destWhId}:{$destLocId}:{$line->lot_id}";

                $srcLotBalance = $lockedLotBalances->get($srcLotKey);
                $destLotBalance = $lockedLotBalances->get($destLotKey);

                if (!$srcLotBalance || !$destLotBalance) {
                    throw new StockDocumentException('Stock lot balance row not found under lock', 'INSUFFICIENT_LOT_STOCK', 409);
                }

                // Deduct source lot (expiry check false as expired lot can be relocated) and increment destination lot
                $this->lotManager->decrementOnHand($srcLotBalance, $qty, checkExpiry: false);
                $this->lotManager->incrementOnHand($destLotBalance, $qty);
            }

            // 3. Mutate Serials
            if ($goods->is_serial_tracked) {
                $serialNos = $line->lineSerials->pluck('serial_no')->toArray();
                $this->serialManager->transferSerials(
                    lockedSerials: $lockedSerials,
                    serialNos: $serialNos,
                    goodsId: $goods->id,
                    sourceWarehouseId: $srcWhId,
                    sourceLocationId: $srcLocId,
                    destWarehouseId: $destWhId,
                    destLocationId: $destLocId
                );
            }

            // 4. Insert Movement Ledgers (TRANSFER_OUT and TRANSFER_IN)
            $negQty = bcmul($qty, '-1', 4);
            $this->movementWriter->write(
                organizationId: $orgId,
                documentId: (string) $document->id,
                documentLineId: $line->id,
                goodsId: $goods->id,
                warehouseId: $srcWhId,
                locationId: $srcLocId,
                movementType: StockMovementType::TRANSFER_OUT,
                quantityDelta: $negQty,
                performedBy: $userId,
                lotId: $line->lot_id
            );

            $this->movementWriter->write(
                organizationId: $orgId,
                documentId: (string) $document->id,
                documentLineId: $line->id,
                goodsId: $goods->id,
                warehouseId: $destWhId,
                locationId: $destLocId,
                movementType: StockMovementType::TRANSFER_IN,
                quantityDelta: $qty,
                performedBy: $userId,
                lotId: $line->lot_id
            );
        }
    }
}
