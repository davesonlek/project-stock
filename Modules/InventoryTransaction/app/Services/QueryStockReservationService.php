<?php

namespace Modules\InventoryTransaction\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\AuthenticationAudit\Services\OrganizationContext;
use Modules\InventoryTransaction\Exceptions\StockDocumentException;
use Modules\InventoryTransaction\Models\StockReservation;

class QueryStockReservationService
{
    public function __construct(
        protected OrganizationContext $context
    ) {}

    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $orgId = $this->context->organizationId();

        $query = StockReservation::where('organization_id', $orgId)
            ->with(['goods.product', 'goods.unit', 'stockLot', 'warehouse', 'location', 'document', 'documentLine', 'creator', 'releaser', 'consumer']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['document_id'])) {
            $query->where('document_id', $filters['document_id']);
        }

        if (!empty($filters['goods_id'])) {
            $query->where('goods_id', $filters['goods_id']);
        }

        if (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        if (!empty($filters['location_id'])) {
            $query->where('location_id', $filters['location_id']);
        }

        if (!empty($filters['expires_before'])) {
            $query->where('expires_at', '<=', $filters['expires_before']);
        }

        $perPage = min((int) ($filters['per_page'] ?? 15), 100);

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function find(string $id): StockReservation
    {
        $orgId = $this->context->organizationId();

        $reservation = StockReservation::where('organization_id', $orgId)
            ->where('id', $id)
            ->with(['goods.product', 'goods.unit', 'stockLot', 'warehouse', 'location', 'document', 'documentLine', 'creator', 'releaser', 'consumer'])
            ->first();

        if (!$reservation) {
            throw new StockDocumentException('Stock reservation not found', 'RESERVATION_NOT_FOUND', 404);
        }

        return $reservation;
    }
}
