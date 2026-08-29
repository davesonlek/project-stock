<?php

namespace Modules\InventoryTransaction\Services;

use Illuminate\Support\Facades\DB;
use Modules\AuthenticationAudit\Services\AuditService;
use Modules\AuthenticationAudit\Services\OrganizationContext;
use Modules\InventoryTransaction\Enums\StockDocumentStatus;
use Modules\InventoryTransaction\Exceptions\StockDocumentException;
use Modules\InventoryTransaction\Models\StockDocument;

class ApproveStockDocumentService
{
    public function __construct(
        protected OrganizationContext $context,
        protected AuditService $auditService
    ) {}

    public function execute(string $id): StockDocument
    {
        $orgId = $this->context->organizationId();
        $userId = $this->context->userId();

        return DB::transaction(function () use ($orgId, $userId, $id) {
            /** @var StockDocument $document */
            $document = StockDocument::forOrganization($orgId)->where('id', $id)->lockForUpdate()->first();

            if (!$document) {
                throw new StockDocumentException('Stock document not found', 'STOCK_DOCUMENT_NOT_FOUND', 404);
            }

            if ($document->status === StockDocumentStatus::APPROVED || $document->status === 'APPROVED') {
                throw new StockDocumentException('Document is already approved', 'DOCUMENT_ALREADY_APPROVED', 409);
            }

            if ($document->status !== StockDocumentStatus::PENDING && $document->status !== 'PENDING') {
                throw new StockDocumentException('Only PENDING documents can be approved', 'INVALID_DOCUMENT_STATE', 409);
            }

            $oldStatus = $document->status instanceof \BackedEnum ? $document->status->value : $document->status;

            $document->update([
                'status' => StockDocumentStatus::APPROVED,
                'approved_by' => $userId,
                'approved_at' => now(),
            ]);

            $newStatus = $document->status instanceof \BackedEnum ? $document->status->value : $document->status;

            // Audit Trail
            $this->auditService->log(
                action: 'STOCK_DOCUMENT_APPROVED',
                entityType: 'StockDocument',
                entityId: (string) $document->id,
                oldData: ['status' => $oldStatus],
                newData: [
                    'status' => $newStatus,
                    'approved_by' => $userId,
                    'approved_at' => $document->approved_at?->toISOString(),
                ]
            );

            return $document->load([
                'sourceWarehouse', 'sourceLocation',
                'destinationWarehouse', 'destinationLocation',
                'supplier', 'creator', 'submitter', 'approver',
                'lines.goods.product', 'lines.goods.unit', 'lines.stockLot', 'lines.lineSerials'
            ]);
        });
    }
}
