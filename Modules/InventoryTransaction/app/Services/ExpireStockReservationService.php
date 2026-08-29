<?php

namespace Modules\InventoryTransaction\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\AuthenticationAudit\Services\AuditService;
use Modules\InventoryTransaction\Enums\StockReservationStatus;
use Modules\InventoryTransaction\Exceptions\StockDocumentException;
use Modules\InventoryTransaction\Models\StockDocument;
use Modules\InventoryTransaction\Models\StockReservation;

class ExpireStockReservationService
{
    public function __construct(
        protected StockBalanceManager $balanceManager,
        protected StockLotManager $lotManager,
        protected SerialNumberManager $serialManager,
        protected AuditService $auditService
    ) {}

    public function expire(StockReservation $reservation, ?string $userId = null): StockReservation
    {
        $orgId = (string) $reservation->organization_id;

        return DB::transaction(function () use ($orgId, $userId, $reservation) {
            // 1. Lock Reservation
            /** @var StockReservation|null $lockedReservation */
            $lockedReservation = StockReservation::where('id', $reservation->id)
                ->lockForUpdate()
                ->first();

            if (!$lockedReservation) {
                throw new StockDocumentException('Reservation not found', 'RESERVATION_NOT_FOUND', 404);
            }

            $currentStatus = $lockedReservation->status instanceof StockReservationStatus ? $lockedReservation->status->value : (string) $lockedReservation->status;
            if ($currentStatus !== StockReservationStatus::ACTIVE->value) {
                // Already consumed/released/expired -> skip safely
                return $lockedReservation;
            }

            // Lock Document if present
            if ($lockedReservation->document_id) {
                StockDocument::where('id', $lockedReservation->document_id)
                    ->lockForUpdate()
                    ->first();
            }

            $goods = $lockedReservation->goods;
            $whId = $lockedReservation->warehouse_id;
            $locId = $lockedReservation->location_id;
            $qty = (string) $lockedReservation->quantity;

            // Lock balances & serials
            $balanceKeys = [[
                'warehouse_id' => $whId,
                'location_id' => $locId,
                'goods_id' => $goods->id,
            ]];
            $lotBalanceKeys = [];
            if ($goods->is_lot_tracked && $lockedReservation->lot_id) {
                $lotBalanceKeys[] = [
                    'warehouse_id' => $whId,
                    'location_id' => $locId,
                    'lot_id' => $lockedReservation->lot_id,
                ];
            }

            $lockedBalances = $this->balanceManager->lockBalances($orgId, $balanceKeys);
            $lockedLotBalances = $this->lotManager->lockLotBalances($orgId, $lotBalanceKeys);

            $serialNos = [];
            if ($goods->is_serial_tracked && $lockedReservation->documentLine) {
                $serialNos = $lockedReservation->documentLine->lineSerials->pluck('serial_no')->toArray();
            }
            $lockedSerials = $this->serialManager->lockSerials($orgId, $serialNos);

            // Decrement reserved
            $balKey = "{$whId}:{$locId}:{$goods->id}";
            $balance = $lockedBalances->get($balKey);
            if ($balance) {
                $this->balanceManager->decrementReserved($balance, $qty);
            }

            if ($goods->is_lot_tracked && $lockedReservation->lot_id) {
                $lotKey = "{$whId}:{$locId}:{$lockedReservation->lot_id}";
                $lotBalance = $lockedLotBalances->get($lotKey);
                if ($lotBalance) {
                    $this->lotManager->decrementReserved($lotBalance, $qty);
                }
            }

            // Release Serials: RESERVED -> IN_STOCK
            if ($goods->is_serial_tracked && !empty($serialNos)) {
                $this->serialManager->releaseSerials(
                    lockedSerials: $lockedSerials,
                    serialNos: $serialNos,
                    goodsId: $goods->id,
                    sourceWarehouseId: $whId,
                    sourceLocationId: $locId
                );
            }

            // Update Reservation Status
            $lockedReservation->update([
                'status' => StockReservationStatus::EXPIRED,
                'released_by' => $userId,
                'released_at' => now(),
            ]);

            // Record Audit Log
            $this->auditService->log(
                action: 'STOCK_RESERVATION_EXPIRED',
                entityType: 'StockReservation',
                entityId: (string) $lockedReservation->id,
                oldData: ['status' => StockReservationStatus::ACTIVE->value],
                newData: [
                    'status' => StockReservationStatus::EXPIRED->value,
                    'released_at' => $lockedReservation->released_at?->toISOString(),
                ]
            );

            return $lockedReservation;
        }, 3);
    }

    /**
     * Process all expired active reservations in batch.
     */
    public function expireAllOverdue(): int
    {
        $now = Carbon::now();
        $expiredReservations = StockReservation::where('status', StockReservationStatus::ACTIVE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->get();

        $count = 0;
        foreach ($expiredReservations as $reservation) {
            $this->expire($reservation);
            $count++;
        }

        return $count;
    }
}
