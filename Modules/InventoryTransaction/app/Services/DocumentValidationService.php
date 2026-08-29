<?php

namespace Modules\InventoryTransaction\Services;

use Modules\AuthenticationAudit\Services\OrganizationContext;
use Modules\InventoryTransaction\Enums\StockDocumentType;
use Modules\InventoryTransaction\Exceptions\StockDocumentException;
use Modules\InventoryTransaction\Models\SerialNumber;
use Modules\InventoryTransaction\Models\StockDocument;
use Modules\InventoryTransaction\Models\StockDocumentLine;
use Modules\InventoryTransaction\Models\StockLot;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Supplier;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;

class DocumentValidationService
{
    public function __construct(
        protected OrganizationContext $context
    ) {}

    /**
     * Validate Document Header structure according to Document Type.
     */
    public function validateHeader(StockDocumentType|string $type, array $data): void
    {
        $orgId = $this->context->organizationId();
        $typeEnum = is_string($type) ? StockDocumentType::from(strtoupper($type)) : $type;

        $sourceWhId = $data['source_warehouse_id'] ?? null;
        $sourceLocId = $data['source_location_id'] ?? null;
        $destWhId = $data['destination_warehouse_id'] ?? null;
        $destLocId = $data['destination_location_id'] ?? null;
        $supplierId = $data['supplier_id'] ?? null;

        match ($typeEnum) {
            StockDocumentType::RECEIVE => $this->validateReceiveHeader($orgId, $sourceWhId, $sourceLocId, $destWhId, $destLocId, $supplierId),
            StockDocumentType::ISSUE => $this->validateIssueHeader($orgId, $sourceWhId, $sourceLocId, $destWhId, $destLocId, $supplierId),
            StockDocumentType::TRANSFER => $this->validateTransferHeader($orgId, $sourceWhId, $sourceLocId, $destWhId, $destLocId, $supplierId),
            StockDocumentType::ADJUSTMENT => $this->validateAdjustmentHeader($orgId, $sourceWhId, $sourceLocId, $destWhId, $destLocId, $supplierId),
        };
    }

    protected function validateReceiveHeader(string $orgId, $sourceWhId, $sourceLocId, $destWhId, $destLocId, $supplierId): void
    {
        if (empty($destWhId) || empty($destLocId)) {
            throw new StockDocumentException('RECEIVE document requires destination warehouse and location', 'INVALID_DOCUMENT_STRUCTURE', 422);
        }

        if (!empty($sourceWhId) || !empty($sourceLocId)) {
            throw new StockDocumentException('RECEIVE document cannot have source warehouse or location', 'INVALID_DOCUMENT_STRUCTURE', 422);
        }

        $this->validateWarehouseLocationConsistency($orgId, $destWhId, $destLocId, 'Destination');

        if (!empty($supplierId)) {
            $supplier = Supplier::forOrganization($orgId)->find($supplierId);
            if (!$supplier) {
                throw new StockDocumentException('Supplier does not belong to the organization', 'CROSS_ORGANIZATION_REFERENCE', 422);
            }
        }
    }

    protected function validateIssueHeader(string $orgId, $sourceWhId, $sourceLocId, $destWhId, $destLocId, $supplierId): void
    {
        if (empty($sourceWhId) || empty($sourceLocId)) {
            throw new StockDocumentException('ISSUE document requires source warehouse and location', 'INVALID_DOCUMENT_STRUCTURE', 422);
        }

        if (!empty($destWhId) || !empty($destLocId) || !empty($supplierId)) {
            throw new StockDocumentException('ISSUE document cannot have destination or supplier', 'INVALID_DOCUMENT_STRUCTURE', 422);
        }

        $this->validateWarehouseLocationConsistency($orgId, $sourceWhId, $sourceLocId, 'Source');
    }

    protected function validateTransferHeader(string $orgId, $sourceWhId, $sourceLocId, $destWhId, $destLocId, $supplierId): void
    {
        if (empty($sourceWhId) || empty($sourceLocId) || empty($destWhId) || empty($destLocId)) {
            throw new StockDocumentException('TRANSFER document requires both source and destination warehouse/location', 'INVALID_DOCUMENT_STRUCTURE', 422);
        }

        if (!empty($supplierId)) {
            throw new StockDocumentException('TRANSFER document cannot have supplier', 'INVALID_DOCUMENT_STRUCTURE', 422);
        }

        if ($sourceLocId == $destLocId) {
            throw new StockDocumentException('Source and destination location cannot be the same', 'SAME_TRANSFER_LOCATION', 422);
        }

        $this->validateWarehouseLocationConsistency($orgId, $sourceWhId, $sourceLocId, 'Source');
        $this->validateWarehouseLocationConsistency($orgId, $destWhId, $destLocId, 'Destination');
    }

    protected function validateAdjustmentHeader(string $orgId, $sourceWhId, $sourceLocId, $destWhId, $destLocId, $supplierId): void
    {
        if (empty($sourceWhId) || empty($sourceLocId)) {
            throw new StockDocumentException('ADJUSTMENT document requires target warehouse and location in source fields', 'INVALID_DOCUMENT_STRUCTURE', 422);
        }

        if (!empty($destWhId) || !empty($destLocId) || !empty($supplierId)) {
            throw new StockDocumentException('ADJUSTMENT document cannot have destination or supplier', 'INVALID_DOCUMENT_STRUCTURE', 422);
        }

        $this->validateWarehouseLocationConsistency($orgId, $sourceWhId, $sourceLocId, 'Source');
    }

    protected function validateWarehouseLocationConsistency(string $orgId, int $warehouseId, int $locationId, string $label): void
    {
        $warehouse = Warehouse::forOrganization($orgId)->find($warehouseId);
        if (!$warehouse) {
            throw new StockDocumentException("{$label} warehouse not found in current organization", 'CROSS_ORGANIZATION_REFERENCE', 422);
        }

        $location = WarehouseLocation::where('warehouse_id', $warehouseId)->find($locationId);
        if (!$location) {
            throw new StockDocumentException("{$label} location does not belong to the selected warehouse", 'INVALID_WAREHOUSE_LOCATION', 422);
        }
    }

    /**
     * Validate Document Line according to Document Type and Goods properties.
     */
    public function validateLine(StockDocument $document, array $data, ?int $existingLineId = null): Goods
    {
        $orgId = $this->context->organizationId();
        $goodsId = $data['goods_id'] ?? null;

        $goods = Goods::forOrganization($orgId)->find($goodsId);
        if (!$goods) {
            throw new StockDocumentException('Goods not found in current organization', 'CROSS_ORGANIZATION_REFERENCE', 422);
        }

        if (!$goods->is_active) {
            throw new StockDocumentException('Goods is currently inactive', 'GOODS_INACTIVE', 422);
        }

        $docType = $document->document_type instanceof StockDocumentType ? $document->document_type : StockDocumentType::from($document->document_type);

        $quantity = isset($data['quantity']) && $data['quantity'] !== '' ? (float) $data['quantity'] : null;
        $countedQuantity = isset($data['counted_quantity']) && $data['counted_quantity'] !== '' ? (float) $data['counted_quantity'] : null;
        $lotId = $data['lot_id'] ?? null;
        $lotNo = $data['lot_no'] ?? null;
        $mfgAt = $data['manufactured_at'] ?? null;
        $expAt = $data['expired_at'] ?? null;
        $serials = $data['serials'] ?? [];

        // Check duplicate line
        $dupQuery = StockDocumentLine::where('document_id', $document->id)
            ->where('goods_id', $goods->id);

        if ($existingLineId) {
            $dupQuery->where('id', '!=', $existingLineId);
        }

        if ($docType === StockDocumentType::RECEIVE) {
            if ($goods->is_lot_tracked) {
                $dupQuery->where('lot_no', $lotNo);
            }
        } else {
            if ($goods->is_lot_tracked) {
                $dupQuery->where('lot_id', $lotId);
            }
        }

        if ($dupQuery->exists()) {
            throw new StockDocumentException('Duplicate document line for the same goods and lot', 'DUPLICATE_DOCUMENT_LINE', 422);
        }

        // Validate line semantics per type
        if ($docType === StockDocumentType::ADJUSTMENT) {
            if ($countedQuantity === null || $countedQuantity < 0) {
                throw new StockDocumentException('ADJUSTMENT requires valid counted_quantity >= 0', 'VALIDATION_ERROR', 422);
            }
            if ($quantity !== null) {
                throw new StockDocumentException('ADJUSTMENT cannot have quantity field', 'VALIDATION_ERROR', 422);
            }
        } else {
            if ($quantity === null || $quantity <= 0) {
                throw new StockDocumentException('Line requires quantity > 0', 'VALIDATION_ERROR', 422);
            }
            if ($countedQuantity !== null) {
                throw new StockDocumentException('Counted quantity is only allowed for ADJUSTMENT', 'VALIDATION_ERROR', 422);
            }
        }

        // Lot Tracking validations
        if ($goods->is_lot_tracked) {
            if ($docType === StockDocumentType::RECEIVE) {
                if (empty($lotNo)) {
                    throw new StockDocumentException('Lot-tracked goods requires lot_no for RECEIVE', 'LOT_REQUIRED', 422);
                }
                if (!empty($mfgAt) && !empty($expAt) && $expAt < $mfgAt) {
                    throw new StockDocumentException('Expired date cannot be earlier than manufactured date', 'VALIDATION_ERROR', 422);
                }
            } else {
                if (empty($lotId)) {
                    throw new StockDocumentException('Lot-tracked goods requires lot_id', 'LOT_REQUIRED', 422);
                }
                $lot = StockLot::forOrganization($orgId)->where('goods_id', $goods->id)->find($lotId);
                if (!$lot) {
                    throw new StockDocumentException('Stock lot does not belong to the goods or organization', 'INVALID_LOT', 422);
                }
            }
        } else {
            if (!empty($lotId) || !empty($lotNo) || !empty($mfgAt) || !empty($expAt)) {
                throw new StockDocumentException('Non lot-tracked goods cannot contain lot information', 'LOT_NOT_ALLOWED', 422);
            }
        }

        // Serial Tracking validations
        if ($goods->is_serial_tracked) {
            $expectedCount = $docType === StockDocumentType::ADJUSTMENT ? (int) $countedQuantity : (int) $quantity;

            if (count($serials) !== $expectedCount) {
                throw new StockDocumentException("Serial count mismatch: expected {$expectedCount} serials, got " . count($serials), 'SERIAL_COUNT_MISMATCH', 422);
            }

            // Check for duplicate serials in the payload
            if (count($serials) !== count(array_unique($serials))) {
                throw new StockDocumentException('Duplicate serial number found in the input list', 'DUPLICATE_SERIAL_NUMBER', 422);
            }

            if ($docType === StockDocumentType::RECEIVE) {
                // For RECEIVE, ensure serial numbers do not already exist in the organization
                $existingSerials = SerialNumber::forOrganization($orgId)
                    ->whereIn('serial_no', $serials)
                    ->pluck('serial_no')
                    ->toArray();

                if (!empty($existingSerials)) {
                    throw new StockDocumentException('Serial number already exists: ' . implode(', ', $existingSerials), 'SERIAL_NUMBER_ALREADY_EXISTS', 422);
                }
            } else {
                // For ISSUE / TRANSFER / ADJUSTMENT, verify serials belong to the goods and org
                $foundSerials = SerialNumber::forOrganization($orgId)
                    ->where('goods_id', $goods->id)
                    ->whereIn('serial_no', $serials)
                    ->get();

                if ($foundSerials->count() !== count($serials)) {
                    throw new StockDocumentException('One or more serial numbers do not belong to the goods or organization', 'INVALID_SERIAL_NUMBER', 422);
                }
            }
        } else {
            if (!empty($serials)) {
                throw new StockDocumentException('Non serial-tracked goods cannot contain serial numbers', 'VALIDATION_ERROR', 422);
            }
        }

        return $goods;
    }
}
