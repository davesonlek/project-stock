<?php

namespace Modules\InventoryTransaction\Services;

use Illuminate\Support\Facades\DB;
use Modules\AuthenticationAudit\Services\AuditService;
use Modules\AuthenticationAudit\Services\OrganizationContext;
use Modules\InventoryTransaction\Enums\StockDocumentStatus;
use Modules\InventoryTransaction\Exceptions\StockDocumentException;
use Modules\InventoryTransaction\Models\StockDocument;

class CancelStockDocumentService
{
    public function __construct(
        protected OrganizationContext $context,
        protected AuditService $auditService
    ) {}

    public function execute(string $id, string $reason): StockDocument
    {
        $orgId = $this->context->organizationId();
        $userId = $this->context->userId();

        return DB::transaction(function () use ($orgId, $userId, $id, $reason) {
            /** @var StockDocument $document */
            $document = StockDocument::forOrganization($orgId)->where('id', $id)->lockForUpdate()->first();

            if (!$document) {
                throw new StockDocumentException('Stock document not found', 'STOCK_DOCUMENT_NOT_FOUND', 404);
            }

            if ($document->status === StockDocumentStatus::CANCELLED || $document->status === 'CANCELLED') {
                throw new StockDocumentException('Document is already cancelled', 'DOCUMENT_ALREADY_CANCELLED', 409);
            }

            $allowedStatuses = [
                StockDocumentStatus::DRAFT,
                StockDocumentStatus::PENDING,
                'DRAFT',
                'PENDING',
            ];

            if (!in_array($document->status, $allowedStatuses, true)) {
                throw new StockDocumentException("Cannot cancel document in {$document->status->value} status", 'INVALID_DOCUMENT_STATE', 409);
            }

            $oldStatus = $document->status instanceof \BackedEnum ? $document->status->value : $document->status;

            // Release all active reservations attached to this document
            $activeReservations = \Modules\InventoryTransaction\Models\StockReservation::where('document_id', $document->id)
                ->where('status', \Modules\InventoryTransaction\Enums\StockReservationStatus::ACTIVE)
                ->get();

            $releaseService = app(\Modules\InventoryTransaction\Services\ReleaseStockReservationService::class);
            foreach ($activeReservations as $res) {
                $releaseService->execute((string) $res->id);
            }

            $document->update([
                'status' => StockDocumentStatus::CANCELLED,
                'cancelled_by' => $userId,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            $newStatus = $document->status instanceof \BackedEnum ? $document->status->value : $document->status;

            $this->auditService->log(
                action: 'STOCK_DOCUMENT_CANCELLED',
                entityType: 'StockDocument',
                entityId: (string) $document->id,
                oldData: ['status' => $oldStatus],
                newData: [
                    'status' => $newStatus,
                    'cancelled_by' => $userId,
                    'cancelled_at' => $document->cancelled_at?->toISOString(),
                    'cancel_reason' => $reason,
                ]
            );

            return $document->load([
                'sourceWarehouse', 'sourceLocation',
                'destinationWarehouse', 'destinationLocation',
                'supplier', 'creator', 'submitter', 'approver', 'canceller',
                'lines.goods.product', 'lines.goods.unit', 'lines.stockLot', 'lines.lineSerials'
            ]);
        });
    }
}
