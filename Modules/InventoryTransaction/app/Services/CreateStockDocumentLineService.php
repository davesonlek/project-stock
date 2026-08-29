<?php

namespace Modules\InventoryTransaction\Services;

use Illuminate\Support\Facades\DB;
use Modules\AuthenticationAudit\Services\AuditService;
use Modules\AuthenticationAudit\Services\OrganizationContext;
use Modules\InventoryTransaction\Enums\StockDocumentStatus;
use Modules\InventoryTransaction\Exceptions\StockDocumentException;
use Modules\InventoryTransaction\Models\SerialNumber;
use Modules\InventoryTransaction\Models\StockDocument;
use Modules\InventoryTransaction\Models\StockDocumentLine;
use Modules\InventoryTransaction\Models\StockDocumentLineSerial;

class CreateStockDocumentLineService
{
    public function __construct(
        protected OrganizationContext $context,
        protected DocumentValidationService $validator,
        protected AuditService $auditService
    ) {}

    public function execute(string $documentId, array $data): StockDocumentLine
    {
        $orgId = $this->context->organizationId();

        return DB::transaction(function () use ($orgId, $documentId, $data) {
            /** @var StockDocument $document */
            $document = StockDocument::forOrganization($orgId)->where('id', $documentId)->lockForUpdate()->first();

            if (!$document) {
                throw new StockDocumentException('Stock document not found', 'STOCK_DOCUMENT_NOT_FOUND', 404);
            }

            if ($document->status !== StockDocumentStatus::DRAFT && $document->status !== 'DRAFT') {
                throw new StockDocumentException('Cannot add lines to non-DRAFT document', 'DOCUMENT_NOT_EDITABLE', 409);
            }

            $goods = $this->validator->validateLine($document, $data);

            $line = StockDocumentLine::create([
                'document_id' => $document->id,
                'goods_id' => $goods->id,
                'lot_id' => $data['lot_id'] ?? null,
                'quantity' => isset($data['quantity']) && $data['quantity'] !== '' ? $data['quantity'] : null,
                'counted_quantity' => isset($data['counted_quantity']) && $data['counted_quantity'] !== '' ? $data['counted_quantity'] : null,
                'unit_cost' => $data['unit_cost'] ?? null,
                'lot_no' => $data['lot_no'] ?? null,
                'manufactured_at' => $data['manufactured_at'] ?? null,
                'expired_at' => $data['expired_at'] ?? null,
                'remarks' => $data['remarks'] ?? null,
            ]);

            // If serials provided, store in stock_document_line_serials
            if (!empty($data['serials'])) {
                foreach ($data['serials'] as $serialNo) {
                    $serialRecord = SerialNumber::forOrganization($orgId)
                        ->where('goods_id', $goods->id)
                        ->where('serial_no', $serialNo)
                        ->first();

                    StockDocumentLineSerial::create([
                        'document_id' => $document->id,
                        'document_line_id' => $line->id,
                        'serial_id' => $serialRecord?->id,
                        'serial_no' => $serialNo,
                        'created_at' => now(),
                    ]);
                }
            }

            $this->auditService->log(
                action: 'STOCK_DOCUMENT_LINE_CREATED',
                entityType: 'StockDocumentLine',
                entityId: (string) $line->id,
                oldData: null,
                newData: $line->toArray()
            );

            return $line->load(['goods.product', 'goods.unit', 'stockLot', 'lineSerials']);
        });
    }
}
