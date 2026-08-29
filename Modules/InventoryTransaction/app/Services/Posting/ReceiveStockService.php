<?php

namespace Modules\InventoryTransaction\Services\Posting;

use Illuminate\Support\Collection;
use Modules\InventoryTransaction\Enums\StockMovementType;
use Modules\InventoryTransaction\Exceptions\StockDocumentException;
use Modules\InventoryTransaction\Models\StockDocument;
use Modules\InventoryTransaction\Models\StockDocumentLine;
use Modules\InventoryTransaction\Models\StockDocumentLineSerial;
use Modules\InventoryTransaction\Services\StockBalanceManager;
use Modules\InventoryTransaction\Services\StockLotManager;
use Modules\InventoryTransaction\Services\SerialNumberManager;
use Modules\InventoryTransaction\Services\StockMovementWriter;

class ReceiveStockService
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
        $destWhId = $document->destination_warehouse_id;
        $destLocId = $document->destination_location_id;

        foreach ($document->lines as $line) {
            $goods = $line->goods;
            $qty = (string) $line->quantity;

            // 1. Mutate Stock Balance
            $balKey = "{$destWhId}:{$destLocId}:{$line->goods_id}";
            $balance = $lockedBalances->get($balKey);
            if (!$balance) {
                throw new StockDocumentException('Stock balance row not found under lock', 'STOCK_BALANCE_ERROR', 500);
            }
            $this->balanceManager->incrementOnHand($balance, $qty);

            $resolvedLotId = null;

            // 2. Lot Handling
            if ($goods->is_lot_tracked) {
                $lot = $this->lotManager->resolveOrCreateLot(
                    orgId: $orgId,
                    goodsId: $goods->id,
                    lotNo: $line->lot_no,
                    manufacturedAt: $line->manufactured_at ? $line->manufactured_at->toDateString() : null,
                    expiredAt: $line->expired_at ? $line->expired_at->toDateString() : null,
                    isReceive: true
                );

                $resolvedLotId = $lot->id;
                if ($line->lot_id !== $lot->id) {
                    $line->update(['lot_id' => $lot->id]);
                }

                $lotKey = "{$destWhId}:{$destLocId}:{$lot->id}";
                $lotBalance = $lockedLotBalances->get($lotKey);
                if (!$lotBalance) {
                    throw new StockDocumentException('Stock lot balance row not found under lock', 'STOCK_BALANCE_ERROR', 500);
                }
                $this->lotManager->incrementOnHand($lotBalance, $qty);
            }

            // 3. Serial Handling
            if ($goods->is_serial_tracked) {
                $serials = $line->lineSerials->pluck('serial_no')->toArray();
                if ((int) $qty != $qty || count($serials) !== (int) $qty) {
                    throw new StockDocumentException(
                        "Serial quantity mismatch: quantity must be integer and equal serial count",
                        'SERIAL_COUNT_MISMATCH',
                        422
                    );
                }

                $createdSerials = $this->serialManager->createSerials(
                    orgId: $orgId,
                    goodsId: $goods->id,
                    lotId: $resolvedLotId,
                    warehouseId: $destWhId,
                    locationId: $destLocId,
                    serialNumbers: $serials
                );

                // Update line serial links
                foreach ($createdSerials as $serial) {
                    StockDocumentLineSerial::where('document_line_id', $line->id)
                        ->where('serial_no', $serial->serial_no)
                        ->update(['serial_id' => $serial->id]);
                }
            }

            // 4. Insert Movement Ledger
            $this->movementWriter->write(
                organizationId: $orgId,
                documentId: (string) $document->id,
                documentLineId: $line->id,
                goodsId: $goods->id,
                warehouseId: $destWhId,
                locationId: $destLocId,
                movementType: StockMovementType::RECEIVE,
                quantityDelta: $qty,
                performedBy: $userId,
                lotId: $resolvedLotId
            );
        }
    }
}
