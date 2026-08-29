<?php

namespace Modules\InventoryTransaction\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\AuthenticationAudit\Services\OrganizationContext;
use Modules\InventoryTransaction\Exceptions\StockDocumentException;
use Modules\InventoryTransaction\Models\StockDocument;

class QueryStockDocumentService
{
    public function __construct(
        protected OrganizationContext $context
    ) {}

    public function list(array $filters): LengthAwarePaginator
    {
        $orgId = $this->context->organizationId();
        $query = StockDocument::forOrganization($orgId)
            ->with([
                'creator',
                'submitter',
                'approver',
                'canceller',
                'supplier',
                'sourceWarehouse',
                'sourceLocation',
                'destinationWarehouse',
                'destinationLocation',
            ]);

        // Search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('document_no', 'ilike', "%{$search}%")
                    ->orWhere('remarks', 'ilike', "%{$search}%");
            });
        }

        // Filters
        if (!empty($filters['document_type'])) {
            $query->where('document_type', strtoupper($filters['document_type']));
        }

        if (!empty($filters['status'])) {
            $query->where('status', strtoupper($filters['status']));
        }

        if (!empty($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (!empty($filters['warehouse_id'])) {
            $whId = $filters['warehouse_id'];
            $query->where(function ($q) use ($whId) {
                $q->where('source_warehouse_id', $whId)
                    ->orWhere('destination_warehouse_id', $whId);
            });
        }

        // Sorting
        $allowedSorts = ['document_no', 'document_type', 'status', 'created_at', 'submitted_at', 'approved_at'];
        $sort = in_array($filters['sort'] ?? null, $allowedSorts, true) ? $filters['sort'] : 'created_at';
        $direction = strtolower($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sort, $direction);

        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        return $query->paginate($perPage);
    }

    public function getById(string $id): StockDocument
    {
        $orgId = $this->context->organizationId();

        $document = StockDocument::forOrganization($orgId)
            ->with([
                'creator',
                'submitter',
                'approver',
                'canceller',
                'poster',
                'supplier',
                'sourceWarehouse',
                'sourceLocation',
                'destinationWarehouse',
                'destinationLocation',
                'lines.goods.product',
                'lines.goods.unit',
                'lines.stockLot',
                'lines.lineSerials',
            ])
            ->where('id', $id)
            ->first();

        if (!$document) {
            throw new StockDocumentException('Stock document not found', 'STOCK_DOCUMENT_NOT_FOUND', 404);
        }

        return $document;
    }
}
