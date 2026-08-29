<?php

namespace Modules\InventoryTransaction\Services;

use Illuminate\Support\Facades\DB;
use Modules\AuthenticationAudit\Services\AuditService;
use Modules\AuthenticationAudit\Services\OrganizationContext;
use Modules\InventoryTransaction\Enums\StockReservationStatus;
use Modules\InventoryTransaction\Exceptions\StockDocumentException;
use Modules\InventoryTransaction\Models\StockDocument;
use Modules\InventoryTransaction\Models\StockReservation;

class ReleaseStockReservationService
{
    public function __construct(
        protected OrganizationContext $context,
        protected StockBalanceManager $balanceManager,
        protected StockLotManager $lotManager,
        protected SerialNumberManager $serialManager,
        protected AuditService $auditService
    ) {}

    public function execute(string $reservationId): StockReservation
    {
        $orgId = $this->context->organizationId();
        $userId = $this->context->userId();

        return DB::transaction(function () use ($orgId, $userId, $reservationId) {
            // 1. Lock Reservation
            /** @var StockReservation|null $reservation */
            $reservation = StockReservation::where('organization_id', $orgId)
                ->where('id', $reservationId)
                ->lockForUpdate()
                ->first();

            if (!$reservation) {
                throw new StockDocumentException('Reservation not found', 'RESERVATION_NOT_FOUND', 404);
            }

            $currentStatus = $reservation->status instanceof StockReservationStatus ? $reservation->status->value : (string) $reservation->status;
            if ($currentStatus !== StockReservationStatus::ACTIVE->value) {
                throw new StockDocumentException("Reservation is not ACTIVE (current status: {$currentStatus})", 'INVALID_RESERVATION_STATE', 409);
            }

            // 2. Lock Document
            if ($reservation->document_id) {
                StockDocument::where('organization_id', $orgId)
                    ->where('id', $reservation->document_id)
                    ->lockForUpdate()
                    ->first();
            }

            $goods = $reservation->goods;
            $whId = $reservation->warehouse_id;
            $locId = $reservation->location_id;
            $qty = (string) $reservation->quantity;

            // 3. Lock Inventory Rows in deterministic order
            $balanceKeys = [[
                'warehouse_id' => $whId,
                'location_id' => $locId,
                'goods_id' => $goods->id,
            ]];
            $lotBalanceKeys = [];
            if ($goods->is_lot_tracked && $reservation->lot_id) {
                $lotBalanceKeys[] = [
                    'warehouse_id' => $whId,
                    'location_id' => $locId,
                    'lot_id' => $reservation->lot_id,
                ];
            }

            $lockedBalances = $this->balanceManager->lockBalances($orgId, $balanceKeys);
            $lockedLotBalances = $this->lotManager->lockLotBalances($orgId, $lotBalanceKeys);

            $serialNos = [];
            if ($goods->is_serial_tracked && $reservation->documentLine) {
                $serialNos = $reservation->documentLine->lineSerials->pluck('serial_no')->toArray();
            }
            $lockedSerials = $this->serialManager->lockSerials($orgId, $serialNos);

            // 4. Decrement Reserved Stock Balance
            $balKey = "{$whId}:{$locId}:{$goods->id}";
            $balance = $lockedBalances->get($balKey);
            if ($balance) {
                $this->balanceManager->decrementReserved($balance, $qty);
            }

            // 5. Decrement Reserved Lot Balance
            if ($goods->is_lot_tracked && $reservation->lot_id) {
                $lotKey = "{$whId}:{$locId}:{$reservation->lot_id}";
                $lotBalance = $lockedLotBalances->get($lotKey);
                if ($lotBalance) {
                    $this->lotManager->decrementReserved($lotBalance, $qty);
                }
            }

            // 6. Release Serials: RESERVED -> IN_STOCK
            if ($goods->is_serial_tracked && !empty($serialNos)) {
                $this->serialManager->releaseSerials(
                    lockedSerials: $lockedSerials,
                    serialNos: $serialNos,
                    goodsId: $goods->id,
                    sourceWarehouseId: $whId,
                    sourceLocationId: $locId
                );
            }

            // 7. Update Reservation Status
            $reservation->update([
                'status' => StockReservationStatus::RELEASED,
                'released_by' => $userId,
                'released_at' => now(),
            ]);

            // 8. Record Audit Log
            $this->auditService->log(
                action: 'STOCK_RESERVATION_RELEASED',
                entityType: 'StockReservation',
                entityId: (string) $reservation->id,
                oldData: ['status' => StockReservationStatus::ACTIVE->value],
                newData: [
                    'status' => StockReservationStatus::RELEASED->value,
                    'released_by' => $userId,
                    'released_at' => $reservation->released_at?->toISOString(),
                ]
            );

            return $reservation->load(['goods.product', 'goods.unit', 'stockLot', 'warehouse', 'location', 'releaser']);
        }, 3);
    }
}
