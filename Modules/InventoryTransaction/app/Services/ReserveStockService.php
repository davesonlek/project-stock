<?php

namespace Modules\InventoryTransaction\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\AuthenticationAudit\Services\AuditService;
use Modules\AuthenticationAudit\Services\OrganizationContext;
use Modules\InventoryTransaction\Enums\SerialNumberStatus;
use Modules\InventoryTransaction\Enums\StockDocumentStatus;
use Modules\InventoryTransaction\Enums\StockDocumentType;
use Modules\InventoryTransaction\Enums\StockReservationStatus;
use Modules\InventoryTransaction\Exceptions\StockDocumentException;
use Modules\InventoryTransaction\Models\SerialNumber;
use Modules\InventoryTransaction\Models\StockDocument;
use Modules\InventoryTransaction\Models\StockDocumentLine;
use Modules\InventoryTransaction\Models\StockReservation;

class ReserveStockService
{
    public function __construct(
        protected OrganizationContext $context,
        protected InventoryLockManager $lockManager,
        protected StockBalanceManager $balanceManager,
        protected StockLotManager $lotManager,
        protected SerialNumberManager $serialManager,
        protected AuditService $auditService
    ) {}

    public function execute(string $documentId, int $lineId, ?string $expiresAt = null): StockReservation
    {
        $orgId = $this->context->organizationId();
        $userId = $this->context->userId();

        return DB::transaction(function () use ($orgId, $userId, $documentId, $lineId, $expiresAt) {
            // 1. Lock Document
            /** @var StockDocument|null $document */
            $document = StockDocument::where('organization_id', $orgId)
                ->where('id', $documentId)
                ->lockForUpdate()
                ->first();

            if (!$document) {
                throw new StockDocumentException('Stock document not found', 'STOCK_DOCUMENT_NOT_FOUND', 404);
            }

            $docType = $document->document_type instanceof StockDocumentType ? $document->document_type->value : (string) $document->document_type;
            if ($docType !== StockDocumentType::ISSUE->value) {
                throw new StockDocumentException('Reservation is only allowed for ISSUE documents', 'INVALID_DOCUMENT_TYPE', 409);
            }

            $docStatus = $document->status instanceof StockDocumentStatus ? $document->status->value : (string) $document->status;
            if (!in_array($docStatus, [StockDocumentStatus::PENDING->value, StockDocumentStatus::APPROVED->value], true)) {
                throw new StockDocumentException("Cannot reserve stock for document in {$docStatus} status. Must be PENDING or APPROVED.", 'INVALID_DOCUMENT_STATE', 409);
            }

            // 2. Lock Document Line
            /** @var StockDocumentLine|null $line */
            $line = StockDocumentLine::where('document_id', $documentId)
                ->where('id', $lineId)
                ->lockForUpdate()
                ->first();

            if (!$line) {
                throw new StockDocumentException('Document line not found', 'DOCUMENT_LINE_NOT_FOUND', 404);
            }

            // 3. Check No Existing ACTIVE Reservation on this line
            $existingActive = StockReservation::where('document_line_id', $lineId)
                ->where('status', StockReservationStatus::ACTIVE)
                ->lockForUpdate()
                ->first();

            if ($existingActive) {
                throw new StockDocumentException('Line already has an active reservation', 'RESERVATION_ALREADY_EXISTS', 409);
            }

            $goods = $line->goods;
            if (!$goods || !$goods->is_active) {
                throw new StockDocumentException('Goods is inactive or not found', 'GOODS_NOT_ACTIVE', 422);
            }

            $srcWhId = $document->source_warehouse_id;
            $srcLocId = $document->source_location_id;
            $qty = (string) $line->quantity;

            // 4. Deterministic Pessimistic Locking
            $balanceKeys = [[
                'warehouse_id' => $srcWhId,
                'location_id' => $srcLocId,
                'goods_id' => $goods->id,
            ]];
            $this->balanceManager->ensureRows($orgId, $balanceKeys);

            $lotBalanceKeys = [];
            if ($goods->is_lot_tracked && $line->lot_id) {
                $lotBalanceKeys[] = [
                    'warehouse_id' => $srcWhId,
                    'location_id' => $srcLocId,
                    'lot_id' => $line->lot_id,
                ];
                $this->lotManager->ensureRows($orgId, $lotBalanceKeys);
            }

            $lockedBalances = $this->balanceManager->lockBalances($orgId, $balanceKeys);
            $lockedLotBalances = $this->lotManager->lockLotBalances($orgId, $lotBalanceKeys);

            $serialNos = [];
            if ($goods->is_serial_tracked) {
                $serialNos = $line->lineSerials->pluck('serial_no')->toArray();
                if (count($serialNos) !== (int) $line->quantity) {
                    throw new StockDocumentException('Serial count mismatch with line quantity', 'SERIAL_COUNT_MISMATCH', 422);
                }
            }
            $lockedSerials = $this->serialManager->lockSerials($orgId, $serialNos);

            // 5. Reserve Stock Balance
            $balKey = "{$srcWhId}:{$srcLocId}:{$goods->id}";
            $balance = $lockedBalances->get($balKey);
            if (!$balance) {
                throw new StockDocumentException('Stock balance row not found', 'INSUFFICIENT_AVAILABLE_STOCK', 409);
            }
            $this->balanceManager->incrementReserved($balance, $qty);

            // 6. Reserve Lot Balance
            if ($goods->is_lot_tracked) {
                $lotKey = "{$srcWhId}:{$srcLocId}:{$line->lot_id}";
                $lotBalance = $lockedLotBalances->get($lotKey);
                if (!$lotBalance) {
                    throw new StockDocumentException('Stock lot balance row not found', 'INSUFFICIENT_LOT_STOCK', 409);
                }
                $this->lotManager->incrementReserved($lotBalance, $qty, checkExpiry: true);
            }

            // 7. Reserve Serials
            if ($goods->is_serial_tracked) {
                $this->serialManager->reserveSerials(
                    lockedSerials: $lockedSerials,
                    serialNos: $serialNos,
                    goodsId: $goods->id,
                    sourceWarehouseId: $srcWhId,
                    sourceLocationId: $srcLocId
                );
            }

            // 8. Create StockReservation Record
            $parsedExpiresAt = $expiresAt ? Carbon::parse($expiresAt)->toISOString() : null;

            /** @var StockReservation $reservation */
            $reservation = StockReservation::create([
                'organization_id' => $orgId,
                'goods_id' => $goods->id,
                'lot_id' => $line->lot_id,
                'warehouse_id' => $srcWhId,
                'location_id' => $srcLocId,
                'document_id' => $documentId,
                'document_line_id' => $lineId,
                'quantity' => $qty,
                'status' => StockReservationStatus::ACTIVE,
                'expires_at' => $parsedExpiresAt,
                'created_by' => $userId,
            ]);

            // 9. Record Audit Log
            $this->auditService->log(
                action: 'STOCK_RESERVED',
                entityType: 'StockReservation',
                entityId: (string) $reservation->id,
                oldData: null,
                newData: [
                    'reservation_id' => (string) $reservation->id,
                    'document_id' => $documentId,
                    'document_line_id' => $lineId,
                    'goods_id' => $goods->id,
                    'lot_id' => $line->lot_id,
                    'warehouse_id' => $srcWhId,
                    'location_id' => $srcLocId,
                    'quantity' => $qty,
                    'expires_at' => $parsedExpiresAt,
                    'created_by' => $userId,
                ]
            );

            return $reservation->load(['goods.product', 'goods.unit', 'stockLot', 'warehouse', 'location', 'document', 'documentLine', 'creator']);
        }, 3);
    }
}
