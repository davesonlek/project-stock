<?php

namespace Modules\InventoryTransaction\Services;

use Illuminate\Support\Facades\DB;
use Modules\AuthenticationAudit\Services\AuditService;
use Modules\AuthenticationAudit\Services\OrganizationContext;
use Modules\InventoryTransaction\Enums\StockDocumentStatus;
use Modules\InventoryTransaction\Exceptions\StockDocumentException;
use Modules\InventoryTransaction\Models\StockDocument;
use Modules\InventoryTransaction\Models\StockDocumentLine;
use Modules\InventoryTransaction\Models\StockDocumentLineSerial;

class DeleteStockDocumentLineService
{
    public function __construct(
        protected OrganizationContext $context,
        protected AuditService $auditService
    ) {}

    public function execute(string $documentId, int $lineId): void
    {
        $orgId = $this->context->organizationId();

        DB::transaction(function () use ($orgId, $documentId, $lineId) {
            /** @var StockDocument $document */
            $document = StockDocument::forOrganization($orgId)->where('id', $documentId)->lockForUpdate()->first();

            if (!$document) {
                throw new StockDocumentException('Stock document not found', 'STOCK_DOCUMENT_NOT_FOUND', 404);
            }

            if ($document->status !== StockDocumentStatus::DRAFT && $document->status !== 'DRAFT') {
                throw new StockDocumentException('Cannot delete lines from non-DRAFT document', 'DOCUMENT_NOT_EDITABLE', 409);
            }

            /** @var StockDocumentLine $line */
            $line = StockDocumentLine::where('document_id', $document->id)->where('id', $lineId)->first();

            if (!$line) {
                throw new StockDocumentException('Stock document line not found', 'STOCK_DOCUMENT_LINE_NOT_FOUND', 404);
            }

            $oldData = $line->toArray();

            StockDocumentLineSerial::where('document_line_id', $line->id)->delete();
            $line->delete();

            $this->auditService->log(
                action: 'STOCK_DOCUMENT_LINE_DELETED',
                entityType: 'StockDocumentLine',
                entityId: (string) $lineId,
                oldData: $oldData,
                newData: null
            );
        });
    }
}
