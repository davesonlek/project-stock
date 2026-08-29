<?php

namespace Modules\InventoryTransaction\Services;

use Illuminate\Support\Facades\DB;
use Modules\AuthenticationAudit\Services\AuditService;
use Modules\AuthenticationAudit\Services\OrganizationContext;
use Modules\InventoryTransaction\Enums\StockDocumentStatus;
use Modules\InventoryTransaction\Exceptions\StockDocumentException;
use Modules\InventoryTransaction\Models\StockDocument;

class SubmitStockDocumentService
{
    public function __construct(
        protected OrganizationContext $context,
        protected DocumentValidationService $validator,
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

            if ($document->status !== StockDocumentStatus::DRAFT && $document->status !== 'DRAFT') {
                throw new StockDocumentException('Only DRAFT documents can be submitted for approval', 'INVALID_DOCUMENT_STATE', 409);
            }

            $lines = $document->lines()->with(['goods', 'lineSerials'])->get();

            if ($lines->isEmpty()) {
                throw new StockDocumentException('Cannot submit a document with no lines', 'DOCUMENT_HAS_NO_LINES', 422);
            }

            // Full Header Validation
            $this->validator->validateHeader($document->document_type, $document->toArray());

            // Full Lines Validation
            foreach ($lines as $line) {
                $lineData = $line->toArray();
                $lineData['serials'] = $line->lineSerials->pluck('serial_no')->toArray();
                $this->validator->validateLine($document, $lineData, $line->id);
            }

            $oldStatus = $document->status instanceof \BackedEnum ? $document->status->value : $document->status;

            $document->update([
                'status' => StockDocumentStatus::PENDING,
                'submitted_by' => $userId,
                'submitted_at' => now(),
            ]);

            $newStatus = $document->status instanceof \BackedEnum ? $document->status->value : $document->status;

            $this->auditService->log(
                action: 'STOCK_DOCUMENT_SUBMITTED',
                entityType: 'StockDocument',
                entityId: (string) $document->id,
                oldData: ['status' => $oldStatus],
                newData: [
                    'status' => $newStatus,
                    'submitted_by' => $userId,
                    'submitted_at' => $document->submitted_at?->toISOString(),
                ]
            );

            return $document->load([
                'sourceWarehouse', 'sourceLocation',
                'destinationWarehouse', 'destinationLocation',
                'supplier', 'creator', 'submitter',
                'lines.goods.product', 'lines.goods.unit', 'lines.stockLot', 'lines.lineSerials'
            ]);
        });
    }
}
