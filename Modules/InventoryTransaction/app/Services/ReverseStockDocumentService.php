<?php

namespace Modules\InventoryTransaction\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\AuthenticationAudit\Services\AuditService;
use Modules\AuthenticationAudit\Services\OrganizationContext;
use Modules\InventoryTransaction\Enums\StockDocumentStatus;
use Modules\InventoryTransaction\Enums\StockDocumentType;
use Modules\InventoryTransaction\Enums\StockMovementType;
use Modules\InventoryTransaction\Enums\StockReservationStatus;
use Modules\InventoryTransaction\Exceptions\StockDocumentException;
use Modules\InventoryTransaction\Http\Resources\StockDocumentResource;
use Modules\InventoryTransaction\Models\StockDocument;
use Modules\InventoryTransaction\Models\StockDocumentLine;
use Modules\InventoryTransaction\Models\StockMovement;
use Modules\InventoryTransaction\Models\StockReservation;

class ReverseStockDocumentService
{
    public function __construct(
        protected OrganizationContext $context,
        protected IdempotencyService $idempotencyService,
        protected StockDocumentNumberGenerator $numberGenerator,
        protected StockBalanceManager $balanceManager,
        protected StockLotManager $lotManager,
        protected SerialNumberManager $serialManager,
        protected StockMovementWriter $movementWriter,
        protected AuditService $auditService
    ) {}

    public function execute(string $documentId, string $idempotencyKey, array $payload = []): array
    {
        $orgId = $this->context->organizationId();
        $userId = $this->context->userId();

        $routeName = request()->route() ? (request()->route()->getName() ?? 'stock.documents.reverse') : 'stock.documents.reverse';
        $httpMethod = request()->method() ?: 'POST';

        $idempotencyRecord = $this->idempotencyService->acquire(
            orgId: $orgId,
            key: $idempotencyKey,
            httpMethod: $httpMethod,
            routeName: $routeName,
            documentId: $documentId,
            payload: $payload
        );

        if ($idempotencyRecord && ($idempotencyRecord->status === \Modules\InventoryTransaction\Enums\IdempotencyStatus::COMPLETED || $idempotencyRecord->status === 'COMPLETED')) {
            return [
                'success' => true,
                'data' => $idempotencyRecord->response_data['data'] ?? $idempotencyRecord->response_data,
                'message' => 'Stock document already reversed (idempotent response)',
            ];
        }

        try {
            $result = DB::transaction(function () use ($orgId, $userId, $documentId, $payload, $idempotencyRecord) {
                // 1. Lock Original Document
                /** @var StockDocument|null $document */
                $document = StockDocument::where('organization_id', $orgId)
                    ->where('id', $documentId)
                    ->lockForUpdate()
                    ->first();

                if (!$document) {
                    throw new StockDocumentException('Stock document not found', 'STOCK_DOCUMENT_NOT_FOUND', 404);
                }

                $docStatus = $document->status instanceof StockDocumentStatus ? $document->status->value : (string) $document->status;
                if ($docStatus === StockDocumentStatus::REVERSED->value) {
                    throw new StockDocumentException('Document is already reversed', 'DOCUMENT_ALREADY_REVERSED', 409);
                }

                if ($docStatus !== StockDocumentStatus::POSTED->value) {
                    throw new StockDocumentException("Cannot reverse document in {$docStatus} status. Must be POSTED.", 'DOCUMENT_NOT_REVERSIBLE', 409);
                }

                if ($document->reversal_of !== null) {
                    throw new StockDocumentException('Cannot reverse a reversal document', 'DOCUMENT_NOT_REVERSIBLE', 409);
                }

                $alreadyReversed = StockDocument::where('reversal_of', $document->id)->exists();
                if ($alreadyReversed) {
                    throw new StockDocumentException('Document has already been reversed', 'DOCUMENT_ALREADY_REVERSED', 409);
                }

                // Check active reservations on original document
                $hasActiveReservation = StockReservation::where('document_id', $document->id)
                    ->where('status', StockReservationStatus::ACTIVE)
                    ->exists();

                if ($hasActiveReservation) {
                    throw new StockDocumentException('Cannot reverse document with active reservations', 'RESERVATION_STATE_CONFLICT', 409);
                }

                // 2. Read Original Movements as Source of Truth
                $originalMovements = StockMovement::where('document_id', $document->id)
                    ->orderBy('id', 'asc')
                    ->get();

                if ($originalMovements->isEmpty()) {
                    throw new StockDocumentException('No stock movements found to reverse for this document', 'NO_MOVEMENTS_TO_REVERSE', 422);
                }

                // 3. Collect Balance and Lot Balance Keys
                $balanceKeysMap = [];
                $lotBalanceKeysMap = [];

                foreach ($originalMovements as $mov) {
                    $bKey = "{$mov->warehouse_id}:{$mov->location_id}:{$mov->goods_id}";
                    $balanceKeysMap[$bKey] = [
                        'warehouse_id' => $mov->warehouse_id,
                        'location_id' => $mov->location_id,
                        'goods_id' => $mov->goods_id,
                    ];

                    if ($mov->lot_id) {
                        $lKey = "{$mov->warehouse_id}:{$mov->location_id}:{$mov->lot_id}";
                        $lotBalanceKeysMap[$lKey] = [
                            'warehouse_id' => $mov->warehouse_id,
                            'location_id' => $mov->location_id,
                            'lot_id' => $mov->lot_id,
                        ];
                    }
                }

                $balanceKeys = array_values($balanceKeysMap);
                $lotBalanceKeys = array_values($lotBalanceKeysMap);

                // 4. Ensure rows exist and Lock deterministic
                $this->balanceManager->ensureRows($orgId, $balanceKeys);
                $this->lotManager->ensureRows($orgId, $lotBalanceKeys);

                $lockedBalances = $this->balanceManager->lockBalances($orgId, $balanceKeys);
                $lockedLotBalances = $this->lotManager->lockLotBalances($orgId, $lotBalanceKeys);

                // Lock all serial numbers related to original document lines
                $serialNos = [];
                $document->load(['lines.lineSerials', 'lines.goods']);
                foreach ($document->lines as $line) {
                    if ($line->goods && $line->goods->is_serial_tracked) {
                        $lineSerials = $line->lineSerials->pluck('serial_no')->toArray();
                        $serialNos = array_merge($serialNos, $lineSerials);
                    }
                }
                $serialNos = array_unique($serialNos);
                $lockedSerials = $this->serialManager->lockSerials($orgId, $serialNos);

                // 5. Pre-flight Feasibility Checks under Lock
                foreach ($originalMovements as $mov) {
                    $compDelta = bcmul((string) $mov->quantity_delta, '-1', 4);
                    // If compensating delta is negative, stock is being reduced
                    if (bccomp($compDelta, '0', 4) < 0) {
                        $needed = bcmul($compDelta, '-1', 4);
                        $balKey = "{$mov->warehouse_id}:{$mov->location_id}:{$mov->goods_id}";
                        $bal = $lockedBalances->get($balKey);

                        $available = $bal ? $this->balanceManager->getAvailable($bal) : '0.0000';
                        if (bccomp($available, $needed, 4) < 0) {
                            throw new StockDocumentException(
                                "Insufficient available stock to reverse document. Available: {$available}, Needed: {$needed}",
                                'REVERSAL_INSUFFICIENT_STOCK',
                                409
                            );
                        }

                        if ($mov->lot_id) {
                            $lotKey = "{$mov->warehouse_id}:{$mov->location_id}:{$mov->lot_id}";
                            $lotBal = $lockedLotBalances->get($lotKey);
                            $lotAvailable = $lotBal ? bcsub((string) $lotBal->on_hand, (string) $lotBal->reserved, 4) : '0.0000';
                            if (bccomp($lotAvailable, $needed, 4) < 0) {
                                throw new StockDocumentException(
                                    "Insufficient lot stock to reverse document for lot ID {$mov->lot_id}",
                                    'REVERSAL_LOT_STOCK_CONFLICT',
                                    409
                                );
                            }
                        }
                    }
                }

                // 6. Create Reversal Document
                $reason = $payload['reason'] ?? 'Document Reversal';
                $revDocNo = $this->numberGenerator->generate(StockDocumentType::REVERSAL);

                $origType = $document->document_type instanceof StockDocumentType ? $document->document_type->value : (string) $document->document_type;

                /** @var StockDocument $revDocument */
                $revDocument = StockDocument::create([
                    'organization_id' => $orgId,
                    'document_no' => $revDocNo,
                    'document_type' => StockDocumentType::REVERSAL,
                    'status' => StockDocumentStatus::POSTED,
                    'source_warehouse_id' => $document->destination_warehouse_id ?? $document->source_warehouse_id,
                    'source_location_id' => $document->destination_location_id ?? $document->source_location_id,
                    'destination_warehouse_id' => $document->source_warehouse_id,
                    'destination_location_id' => $document->source_location_id,
                    'supplier_id' => $document->supplier_id,
                    'created_by' => $userId,
                    'approved_by' => $userId,
                    'approved_at' => now(),
                    'posted_by' => $userId,
                    'posted_at' => now(),
                    'reversal_of' => $document->id,
                    'remarks' => $reason,
                ]);

                // Copy lines for UI/Audit reference and build line map
                $lineMap = [];
                foreach ($document->lines as $line) {
                    $revLine = StockDocumentLine::create([
                        'document_id' => $revDocument->id,
                        'goods_id' => $line->goods_id,
                        'lot_id' => $line->lot_id,
                        'quantity' => $line->quantity,
                        'unit_price' => $line->unit_price,
                        'remarks' => "Reversal of line #{$line->id}",
                    ]);
                    $lineMap[$line->id] = $revLine->id;
                }

                // 7. Apply Compensating Inventory Changes & Insert Compensating Movements
                foreach ($originalMovements as $origMovement) {
                    $compDelta = bcmul((string) $origMovement->quantity_delta, '-1', 4);

                    // Update Stock Balance
                    $balKey = "{$origMovement->warehouse_id}:{$origMovement->location_id}:{$origMovement->goods_id}";
                    $bal = $lockedBalances->get($balKey);
                    $newOnHand = bcadd((string) $bal->on_hand, $compDelta, 4);
                    $bal->update(['on_hand' => $newOnHand]);

                    // Update Lot Balance
                    if ($origMovement->lot_id) {
                        $lotKey = "{$origMovement->warehouse_id}:{$origMovement->location_id}:{$origMovement->lot_id}";
                        $lotBal = $lockedLotBalances->get($lotKey);
                        $newLotOnHand = bcadd((string) $lotBal->on_hand, $compDelta, 4);
                        $lotBal->update(['on_hand' => $newLotOnHand]);
                    }

                    $revLineId = $lineMap[$origMovement->document_line_id] ?? $origMovement->document_line_id;

                    // Insert Compensating Movement
                    $this->movementWriter->write(
                        organizationId: $orgId,
                        documentId: (string) $revDocument->id,
                        documentLineId: $revLineId,
                        goodsId: $origMovement->goods_id,
                        warehouseId: $origMovement->warehouse_id,
                        locationId: $origMovement->location_id,
                        movementType: StockMovementType::REVERSAL,
                        quantityDelta: $compDelta,
                        performedBy: $userId,
                        lotId: $origMovement->lot_id,
                        reversalOf: $origMovement->id
                    );
                }

                // 8. Reconcile Serials based on Original Document Type
                foreach ($document->lines as $line) {
                    if ($line->goods && $line->goods->is_serial_tracked) {
                        $lineSerials = $line->lineSerials->pluck('serial_no')->toArray();
                        if (!empty($lineSerials)) {
                            if ($origType === StockDocumentType::RECEIVE->value) {
                                $this->serialManager->reverseReceiveSerials(
                                    lockedSerials: $lockedSerials,
                                    serialNos: $lineSerials,
                                    goodsId: $line->goods_id,
                                    origWarehouseId: (int) $document->destination_warehouse_id,
                                    origLocationId: (int) $document->destination_location_id
                                );
                            } elseif ($origType === StockDocumentType::ISSUE->value) {
                                $this->serialManager->reverseIssueSerials(
                                    lockedSerials: $lockedSerials,
                                    serialNos: $lineSerials,
                                    goodsId: $line->goods_id,
                                    origWarehouseId: (int) $document->source_warehouse_id,
                                    origLocationId: (int) $document->source_location_id
                                );
                            } elseif ($origType === StockDocumentType::TRANSFER->value) {
                                $this->serialManager->reverseTransferSerials(
                                    lockedSerials: $lockedSerials,
                                    serialNos: $lineSerials,
                                    goodsId: $line->goods_id,
                                    origSourceWarehouseId: (int) $document->source_warehouse_id,
                                    origSourceLocationId: (int) $document->source_location_id,
                                    origDestWarehouseId: (int) $document->destination_warehouse_id,
                                    origDestLocationId: (int) $document->destination_location_id
                                );
                            }
                        }
                    }
                }

                // 9. Update Original Document Status
                $document->update(['status' => StockDocumentStatus::REVERSED]);

                // 10. Record Audit Log
                $this->auditService->log(
                    action: 'STOCK_DOCUMENT_REVERSED',
                    entityType: 'StockDocument',
                    entityId: (string) $document->id,
                    oldData: ['status' => StockDocumentStatus::POSTED->value],
                    newData: [
                        'status' => StockDocumentStatus::REVERSED->value,
                        'original_document_id' => (string) $document->id,
                        'reversal_document_id' => (string) $revDocument->id,
                        'reversal_document_no' => $revDocNo,
                        'reason' => $reason,
                        'user_id' => $userId,
                        'movement_count' => $originalMovements->count(),
                    ]
                );

                $reloaded = $revDocument->load([
                    'sourceWarehouse', 'sourceLocation',
                    'destinationWarehouse', 'destinationLocation',
                    'supplier', 'creator', 'poster',
                    'lines.goods.product', 'lines.goods.unit', 'lines.stockLot'
                ]);

                if ($idempotencyRecord) {
                    $this->idempotencyService->complete($idempotencyRecord, 200, [
                        'success' => true,
                        'data' => (new StockDocumentResource($reloaded))->resolve(),
                        'message' => 'Stock document reversed successfully',
                    ]);
                }

                return [
                    'original_document' => $document,
                    'reversal_document' => $reloaded,
                    'resource' => (new StockDocumentResource($reloaded))->resolve(),
                ];
            }, 3);

            return [
                'success' => true,
                'data' => $result['resource'],
                'message' => 'Stock document reversed successfully',
            ];
        } catch (\Throwable $e) {
            throw $e;
        }
    }
}
