<?php

namespace Modules\InventoryTransaction\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Modules\AuthenticationAudit\Services\AuditService;
use Modules\AuthenticationAudit\Services\OrganizationContext;
use Modules\InventoryTransaction\Enums\IdempotencyStatus;
use Modules\InventoryTransaction\Enums\StockDocumentStatus;
use Modules\InventoryTransaction\Enums\StockDocumentType;
use Modules\InventoryTransaction\Exceptions\StockDocumentException;
use Modules\InventoryTransaction\Models\StockDocument;
use Modules\InventoryTransaction\Services\Posting\AdjustmentStockService;
use Modules\InventoryTransaction\Services\Posting\IssueStockService;
use Modules\InventoryTransaction\Services\Posting\ReceiveStockService;
use Modules\InventoryTransaction\Services\Posting\TransferStockService;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;

class PostStockDocumentService
{
    public function __construct(
        protected OrganizationContext $context,
        protected IdempotencyService $idempotencyService,
        protected InventoryLockManager $lockManager,
        protected StockLotManager $lotManager,
        protected ReceiveStockService $receiveService,
        protected IssueStockService $issueService,
        protected TransferStockService $transferService,
        protected AdjustmentStockService $adjustmentService,
        protected AuditService $auditService
    ) {}

    public function execute(string $documentId, string $idempotencyKeyString, array $payload = []): array
    {
        $orgId = $this->context->organizationId();
        $userId = $this->context->userId();
        $routeName = request()->route() ? request()->route()->getName() ?? 'stock.documents.post' : 'stock.documents.post';
        $httpMethod = request()->method() ?: 'POST';

        // 1. Acquire / Validate Idempotency Key outside or inside transaction
        // First check if completed response already cached
        $idempotencyRecord = $this->idempotencyService->acquire(
            orgId: $orgId,
            key: $idempotencyKeyString,
            httpMethod: $httpMethod,
            routeName: $routeName,
            documentId: $documentId,
            payload: $payload
        );

        if ($idempotencyRecord && ($idempotencyRecord->status === IdempotencyStatus::COMPLETED || $idempotencyRecord->status === 'COMPLETED')) {
            return [
                'document' => StockDocument::forOrganization($orgId)
                    ->with([
                        'sourceWarehouse', 'sourceLocation',
                        'destinationWarehouse', 'destinationLocation',
                        'supplier', 'creator', 'submitter', 'approver', 'poster',
                        'lines.goods.product', 'lines.goods.unit', 'lines.stockLot', 'lines.lineSerials'
                    ])
                    ->find($documentId),
                'response_data' => $idempotencyRecord->response_data,
                'is_replay' => true,
            ];
        }

        // 2. Perform Stock Posting within ACID DB Transaction with up to 3 deadlock retry attempts
        $document = DB::transaction(function () use ($orgId, $userId, $documentId, $idempotencyRecord) {
            // 2.1 Lock Stock Document
            /** @var StockDocument|null $doc */
            $doc = StockDocument::forOrganization($orgId)
                ->where('id', $documentId)
                ->lockForUpdate()
                ->first();

            if (!$doc) {
                throw new StockDocumentException('Stock document not found', 'STOCK_DOCUMENT_NOT_FOUND', 404);
            }

            if ($doc->status === StockDocumentStatus::POSTED || $doc->status === 'POSTED') {
                throw new StockDocumentException('Stock document is already posted', 'DOCUMENT_ALREADY_POSTED', 409);
            }

            if ($doc->status !== StockDocumentStatus::APPROVED && $doc->status !== 'APPROVED') {
                throw new StockDocumentException('Only APPROVED documents can be posted', 'INVALID_DOCUMENT_STATE', 409);
            }

            $lines = $doc->lines()->with(['goods', 'lineSerials'])->get();
            if ($lines->isEmpty()) {
                throw new StockDocumentException('Cannot post a document with no lines', 'DOCUMENT_HAS_NO_LINES', 422);
            }

            // 2.2 Re-validate Master Data under lock
            $this->revalidateMasterData($orgId, $doc, $lines);

            // 2.3 Prepare balance and lot keys to lock
            $docType = $doc->document_type instanceof StockDocumentType ? $doc->document_type : StockDocumentType::from($doc->document_type);

            $balanceKeys = [];
            $lotBalanceKeys = [];
            $serialNosToLock = [];

            foreach ($lines as $line) {
                $goods = $line->goods;

                // Collect balance keys
                if ($docType === StockDocumentType::RECEIVE) {
                    $balanceKeys[] = [
                        'warehouse_id' => $doc->destination_warehouse_id,
                        'location_id' => $doc->destination_location_id,
                        'goods_id' => $goods->id,
                    ];

                    if ($goods->is_lot_tracked) {
                        $lot = $this->lotManager->resolveOrCreateLot(
                            orgId: $orgId,
                            goodsId: $goods->id,
                            lotNo: $line->lot_no,
                            manufacturedAt: $line->manufactured_at ? $line->manufactured_at->toDateString() : null,
                            expiredAt: $line->expired_at ? $line->expired_at->toDateString() : null,
                            isReceive: true
                        );
                        $lotBalanceKeys[] = [
                            'warehouse_id' => $doc->destination_warehouse_id,
                            'location_id' => $doc->destination_location_id,
                            'lot_id' => $lot->id,
                        ];
                    }
                } elseif ($docType === StockDocumentType::ISSUE) {
                    $balanceKeys[] = [
                        'warehouse_id' => $doc->source_warehouse_id,
                        'location_id' => $doc->source_location_id,
                        'goods_id' => $goods->id,
                    ];

                    if ($goods->is_lot_tracked && $line->lot_id) {
                        $lotBalanceKeys[] = [
                            'warehouse_id' => $doc->source_warehouse_id,
                            'location_id' => $doc->source_location_id,
                            'lot_id' => $line->lot_id,
                        ];
                    }

                    if ($goods->is_serial_tracked) {
                        $serialNosToLock = array_merge($serialNosToLock, $line->lineSerials->pluck('serial_no')->toArray());
                    }
                } elseif ($docType === StockDocumentType::TRANSFER) {
                    $balanceKeys[] = [
                        'warehouse_id' => $doc->source_warehouse_id,
                        'location_id' => $doc->source_location_id,
                        'goods_id' => $goods->id,
                    ];
                    $balanceKeys[] = [
                        'warehouse_id' => $doc->destination_warehouse_id,
                        'location_id' => $doc->destination_location_id,
                        'goods_id' => $goods->id,
                    ];

                    if ($goods->is_lot_tracked && $line->lot_id) {
                        $lotBalanceKeys[] = [
                            'warehouse_id' => $doc->source_warehouse_id,
                            'location_id' => $doc->source_location_id,
                            'lot_id' => $line->lot_id,
                        ];
                        $lotBalanceKeys[] = [
                            'warehouse_id' => $doc->destination_warehouse_id,
                            'location_id' => $doc->destination_location_id,
                            'lot_id' => $line->lot_id,
                        ];
                    }

                    if ($goods->is_serial_tracked) {
                        $serialNosToLock = array_merge($serialNosToLock, $line->lineSerials->pluck('serial_no')->toArray());
                    }
                } elseif ($docType === StockDocumentType::ADJUSTMENT) {
                    $balanceKeys[] = [
                        'warehouse_id' => $doc->source_warehouse_id,
                        'location_id' => $doc->source_location_id,
                        'goods_id' => $goods->id,
                    ];

                    if ($goods->is_lot_tracked && $line->lot_id) {
                        $lotBalanceKeys[] = [
                            'warehouse_id' => $doc->source_warehouse_id,
                            'location_id' => $doc->source_location_id,
                            'lot_id' => $line->lot_id,
                        ];
                    }
                }
            }

            // Deduplicate balance keys
            $uniqueBalanceKeys = $this->deduplicateKeys($balanceKeys, ['warehouse_id', 'location_id', 'goods_id']);
            $uniqueLotBalanceKeys = $this->deduplicateKeys($lotBalanceKeys, ['warehouse_id', 'location_id', 'lot_id']);
            $uniqueSerialNos = array_unique($serialNosToLock);

            // 2.4 Execute Global Deterministic Inventory Lock
            $locks = $this->lockManager->lockAllRequiredInventory(
                orgId: $orgId,
                document: $doc,
                balanceKeys: $uniqueBalanceKeys,
                lotBalanceKeys: $uniqueLotBalanceKeys,
                serialNos: $uniqueSerialNos
            );

            // 2.5 Dispatch to Specific Posting Domain Service
            match ($docType) {
                StockDocumentType::RECEIVE => $this->receiveService->post(
                    orgId: $orgId,
                    userId: $userId,
                    document: $doc,
                    lockedBalances: $locks['balances'],
                    lockedLotBalances: $locks['lotBalances']
                ),
                StockDocumentType::ISSUE => $this->issueService->post(
                    orgId: $orgId,
                    userId: $userId,
                    document: $doc,
                    lockedBalances: $locks['balances'],
                    lockedLotBalances: $locks['lotBalances'],
                    lockedSerials: $locks['serials']
                ),
                StockDocumentType::TRANSFER => $this->transferService->post(
                    orgId: $orgId,
                    userId: $userId,
                    document: $doc,
                    lockedBalances: $locks['balances'],
                    lockedLotBalances: $locks['lotBalances'],
                    lockedSerials: $locks['serials']
                ),
                StockDocumentType::ADJUSTMENT => $this->adjustmentService->post(
                    orgId: $orgId,
                    userId: $userId,
                    document: $doc,
                    lockedBalances: $locks['balances'],
                    lockedLotBalances: $locks['lotBalances']
                ),
            };

            // 2.6 Update Document Status to POSTED
            $doc->update([
                'status' => StockDocumentStatus::POSTED,
                'posted_by' => $userId,
                'posted_at' => now(),
            ]);

            // 2.7 Atomic Audit Trail
            $this->auditService->log(
                action: 'STOCK_DOCUMENT_POSTED',
                entityType: 'StockDocument',
                entityId: (string) $doc->id,
                oldData: ['status' => 'APPROVED'],
                newData: [
                    'status' => 'POSTED',
                    'document_no' => $doc->document_no,
                    'document_type' => $docType->value,
                    'posted_by' => $userId,
                    'posted_at' => $doc->posted_at?->toISOString(),
                ]
            );

            // 2.8 Complete Idempotency Record
            if ($idempotencyRecord) {
                $responseData = [
                    'id' => (string) $doc->id,
                    'document_no' => $doc->document_no,
                    'document_type' => $docType->value,
                    'status' => 'POSTED',
                    'posted_at' => $doc->posted_at?->toISOString(),
                ];
                $this->idempotencyService->complete($idempotencyRecord, 200, $responseData);
            }

            return $doc;
        }, 3);

        $reloaded = $document->fresh([
            'sourceWarehouse', 'sourceLocation',
            'destinationWarehouse', 'destinationLocation',
            'supplier', 'creator', 'submitter', 'approver', 'poster',
            'lines.goods.product', 'lines.goods.unit', 'lines.stockLot', 'lines.lineSerials'
        ]);

        return [
            'document' => $reloaded,
            'response_data' => [
                'id' => (string) $reloaded->id,
                'document_no' => $reloaded->document_no,
                'document_type' => $reloaded->document_type instanceof \BackedEnum ? $reloaded->document_type->value : $reloaded->document_type,
                'status' => 'POSTED',
                'posted_at' => $reloaded->posted_at?->toISOString(),
            ],
            'is_replay' => false,
        ];
    }

    protected function revalidateMasterData(string $orgId, StockDocument $doc, Collection $lines): void
    {
        if ($doc->source_warehouse_id) {
            $wh = Warehouse::forOrganization($orgId)->find($doc->source_warehouse_id);
            if (!$wh || !$wh->is_active) {
                throw new StockDocumentException('Source warehouse is inactive or missing', 'CROSS_ORGANIZATION_REFERENCE', 422);
            }
            $loc = WarehouseLocation::where('warehouse_id', $wh->id)->find($doc->source_location_id);
            if (!$loc || !$loc->is_active) {
                throw new StockDocumentException('Source location is inactive or invalid', 'CROSS_ORGANIZATION_REFERENCE', 422);
            }
        }

        if ($doc->destination_warehouse_id) {
            $wh = Warehouse::forOrganization($orgId)->find($doc->destination_warehouse_id);
            if (!$wh || !$wh->is_active) {
                throw new StockDocumentException('Destination warehouse is inactive or missing', 'CROSS_ORGANIZATION_REFERENCE', 422);
            }
            $loc = WarehouseLocation::where('warehouse_id', $wh->id)->find($doc->destination_location_id);
            if (!$loc || !$loc->is_active) {
                throw new StockDocumentException('Destination location is inactive or invalid', 'CROSS_ORGANIZATION_REFERENCE', 422);
            }
        }

        foreach ($lines as $line) {
            $goods = Goods::forOrganization($orgId)->find($line->goods_id);
            if (!$goods || !$goods->is_active) {
                throw new StockDocumentException("Goods '{$line->goods_id}' is inactive or missing", 'GOODS_INACTIVE', 422);
            }
        }
    }

    protected function deduplicateKeys(array $keys, array $fields): array
    {
        $unique = [];
        $result = [];
        foreach ($keys as $k) {
            $hash = implode(':', array_map(fn($f) => $k[$f] ?? '', $fields));
            if (!isset($unique[$hash])) {
                $unique[$hash] = true;
                $result[] = $k;
            }
        }
        return $result;
    }
}
