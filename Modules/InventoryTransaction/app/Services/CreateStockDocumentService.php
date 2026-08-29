<?php

namespace Modules\InventoryTransaction\Services;

use Illuminate\Support\Facades\DB;
use Modules\AuthenticationAudit\Services\AuditService;
use Modules\AuthenticationAudit\Services\OrganizationContext;
use Modules\InventoryTransaction\Enums\StockDocumentStatus;
use Modules\InventoryTransaction\Enums\StockDocumentType;
use Modules\InventoryTransaction\Models\StockDocument;

class CreateStockDocumentService
{
    public function __construct(
        protected OrganizationContext $context,
        protected DocumentValidationService $validator,
        protected StockDocumentNumberGenerator $numberGenerator,
        protected AuditService $auditService
    ) {}

    public function execute(array $data): StockDocument
    {
        $orgId = $this->context->organizationId();
        $userId = $this->context->userId();
        $docType = StockDocumentType::from(strtoupper($data['document_type']));

        // Validate Header
        $this->validator->validateHeader($docType, $data);

        return DB::transaction(function () use ($orgId, $userId, $docType, $data) {
            $documentNo = $this->numberGenerator->generate($docType);

            $document = StockDocument::create([
                'organization_id' => $orgId,
                'document_no' => $documentNo,
                'document_type' => $docType,
                'status' => StockDocumentStatus::DRAFT,
                'source_warehouse_id' => $data['source_warehouse_id'] ?? null,
                'source_location_id' => $data['source_location_id'] ?? null,
                'destination_warehouse_id' => $data['destination_warehouse_id'] ?? null,
                'destination_location_id' => $data['destination_location_id'] ?? null,
                'supplier_id' => $data['supplier_id'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'created_by' => $userId,
            ]);

            $this->auditService->log(
                action: 'STOCK_DOCUMENT_CREATED',
                entityType: 'StockDocument',
                entityId: (string) $document->id,
                oldData: null,
                newData: $document->toArray()
            );

            return $document->load(['sourceWarehouse', 'sourceLocation', 'destinationWarehouse', 'destinationLocation', 'supplier', 'creator']);
        });
    }
}
