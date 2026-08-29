<?php

namespace Modules\InventoryTransaction\Services;

use Illuminate\Support\Facades\DB;
use Modules\AuthenticationAudit\Services\AuditService;
use Modules\AuthenticationAudit\Services\OrganizationContext;
use Modules\InventoryTransaction\Enums\StockDocumentStatus;
use Modules\InventoryTransaction\Enums\StockDocumentType;
use Modules\InventoryTransaction\Exceptions\StockDocumentException;
use Modules\InventoryTransaction\Models\StockDocument;

class UpdateStockDocumentService
{
    public function __construct(
        protected OrganizationContext $context,
        protected DocumentValidationService $validator,
        protected AuditService $auditService
    ) {}

    public function execute(string $id, array $data): StockDocument
    {
        $orgId = $this->context->organizationId();

        return DB::transaction(function () use ($orgId, $id, $data) {
            /** @var StockDocument $document */
            $document = StockDocument::forOrganization($orgId)->where('id', $id)->lockForUpdate()->first();

            if (!$document) {
                throw new StockDocumentException('Stock document not found', 'STOCK_DOCUMENT_NOT_FOUND', 404);
            }

            if ($document->status !== StockDocumentStatus::DRAFT && $document->status !== 'DRAFT') {
                throw new StockDocumentException('Only DRAFT documents can be edited', 'DOCUMENT_NOT_EDITABLE', 409);
            }

            $targetType = isset($data['document_type'])
                ? StockDocumentType::from(strtoupper($data['document_type']))
                : $document->document_type;

            if ($targetType !== $document->document_type) {
                if ($document->lines()->count() > 0) {
                    throw new StockDocumentException('Cannot change document type after lines have been added', 'DOCUMENT_TYPE_LOCKED', 409);
                }
            }

            // Validate header for new or merged fields
            $mergedData = array_merge($document->toArray(), $data);
            $this->validator->validateHeader($targetType, $mergedData);

            $oldData = $document->toArray();

            $document->update([
                'document_type' => $targetType,
                'source_warehouse_id' => $data['source_warehouse_id'] ?? $document->source_warehouse_id,
                'source_location_id' => $data['source_location_id'] ?? $document->source_location_id,
                'destination_warehouse_id' => $data['destination_warehouse_id'] ?? $document->destination_warehouse_id,
                'destination_location_id' => $data['destination_location_id'] ?? $document->destination_location_id,
                'supplier_id' => array_key_exists('supplier_id', $data) ? $data['supplier_id'] : $document->supplier_id,
                'remarks' => array_key_exists('remarks', $data) ? $data['remarks'] : $document->remarks,
            ]);

            $this->auditService->log(
                action: 'STOCK_DOCUMENT_UPDATED',
                entityType: 'StockDocument',
                entityId: (string) $document->id,
                oldData: $oldData,
                newData: $document->toArray()
            );

            return $document->load(['sourceWarehouse', 'sourceLocation', 'destinationWarehouse', 'destinationLocation', 'supplier', 'creator']);
        });
    }
}
