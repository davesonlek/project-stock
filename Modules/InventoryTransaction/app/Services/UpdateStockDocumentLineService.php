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

class UpdateStockDocumentLineService
{
    public function __construct(
        protected OrganizationContext $context,
        protected DocumentValidationService $validator,
        protected AuditService $auditService
    ) {}

    public function execute(string $documentId, int $lineId, array $data): StockDocumentLine
    {
        $orgId = $this->context->organizationId();

        return DB::transaction(function () use ($orgId, $documentId, $lineId, $data) {
            /** @var StockDocument $document */
            $document = StockDocument::forOrganization($orgId)->where('id', $documentId)->lockForUpdate()->first();

            if (!$document) {
                throw new StockDocumentException('Stock document not found', 'STOCK_DOCUMENT_NOT_FOUND', 404);
            }

            if ($document->status !== StockDocumentStatus::DRAFT && $document->status !== 'DRAFT') {
                throw new StockDocumentException('Cannot edit lines in non-DRAFT document', 'DOCUMENT_NOT_EDITABLE', 409);
            }

            /** @var StockDocumentLine $line */
            $line = StockDocumentLine::where('document_id', $document->id)->where('id', $lineId)->first();

            if (!$line) {
                throw new StockDocumentException('Stock document line not found', 'STOCK_DOCUMENT_LINE_NOT_FOUND', 404);
            }

            $mergedData = array_merge($line->toArray(), $data);
            $goods = $this->validator->validateLine($document, $mergedData, $line->id);

            $oldData = $line->toArray();

            $line->update([
                'goods_id' => $goods->id,
                'lot_id' => array_key_exists('lot_id', $data) ? $data['lot_id'] : $line->lot_id,
                'quantity' => array_key_exists('quantity', $data) ? $data['quantity'] : $line->quantity,
                'counted_quantity' => array_key_exists('counted_quantity', $data) ? $data['counted_quantity'] : $line->counted_quantity,
                'unit_cost' => array_key_exists('unit_cost', $data) ? $data['unit_cost'] : $line->unit_cost,
                'lot_no' => array_key_exists('lot_no', $data) ? $data['lot_no'] : $line->lot_no,
                'manufactured_at' => array_key_exists('manufactured_at', $data) ? $data['manufactured_at'] : $line->manufactured_at,
                'expired_at' => array_key_exists('expired_at', $data) ? $data['expired_at'] : $line->expired_at,
                'remarks' => array_key_exists('remarks', $data) ? $data['remarks'] : $line->remarks,
            ]);

            // Re-sync serials if provided in data
            if (array_key_exists('serials', $data)) {
                StockDocumentLineSerial::where('document_line_id', $line->id)->delete();

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
            }

            $this->auditService->log(
                action: 'STOCK_DOCUMENT_LINE_UPDATED',
                entityType: 'StockDocumentLine',
                entityId: (string) $line->id,
                oldData: $oldData,
                newData: $line->toArray()
            );

            return $line->load(['goods.product', 'goods.unit', 'stockLot', 'lineSerials']);
        });
    }
}
