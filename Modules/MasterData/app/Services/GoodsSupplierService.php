<?php

namespace Modules\MasterData\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\AuthenticationAudit\Services\AuditService;
use Modules\AuthenticationAudit\Services\OrganizationContext;
use Modules\MasterData\Exceptions\MasterDataException;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\GoodsSupplier;
use Modules\MasterData\Models\Supplier;

class GoodsSupplierService
{
    public function __construct(
        protected OrganizationContext $context
    ) {}

    public function list(array $filters = []): LengthAwarePaginator
    {
        $perPage = min((int) ($filters['per_page'] ?? 20), 100);
        if ($perPage <= 0) {
            $perPage = 20;
        }

        $query = GoodsSupplier::forOrganization($this->context->organizationId())
            ->with(['supplier', 'goods']);

        if (!empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        if (!empty($filters['goods_id'])) {
            $query->where('goods_id', $filters['goods_id']);
        }

        if (isset($filters['is_primary']) && $filters['is_primary'] !== '') {
            $query->where('is_primary', filter_var($filters['is_primary'], FILTER_VALIDATE_BOOLEAN));
        }

        $sortField = in_array($filters['sort'] ?? '', ['id', 'purchase_price', 'lead_time_days', 'created_at'], true) ? $filters['sort'] : 'id';
        $direction = strtolower($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sortField, $direction)->paginate($perPage);
    }

    public function getById(int $id): GoodsSupplier
    {
        $goodsSupplier = GoodsSupplier::forOrganization($this->context->organizationId())
            ->with(['supplier', 'goods'])
            ->find($id);

        if (!$goodsSupplier) {
            throw new MasterDataException('Goods supplier relation not found', 'MASTER_DATA_NOT_FOUND', 404);
        }

        return $goodsSupplier;
    }

    public function create(array $data): GoodsSupplier
    {
        // Cross-tenant check
        $supplier = Supplier::forOrganization($this->context->organizationId())->find($data['supplier_id']);
        if (!$supplier) {
            throw new MasterDataException('Invalid Supplier for current organization', 'CROSS_ORGANIZATION_REFERENCE', 422);
        }

        $goods = Goods::forOrganization($this->context->organizationId())->find($data['goods_id']);
        if (!$goods) {
            throw new MasterDataException('Invalid Goods for current organization', 'CROSS_ORGANIZATION_REFERENCE', 422);
        }

        // Duplicate check
        $existing = GoodsSupplier::forOrganization($this->context->organizationId())
            ->where('supplier_id', $data['supplier_id'])
            ->where('goods_id', $data['goods_id'])
            ->first();

        if ($existing) {
            throw new MasterDataException('Supplier is already linked to this goods', 'DUPLICATE_RELATION', 422);
        }

        return DB::transaction(function () use ($data) {
            $isPrimary = $data['is_primary'] ?? false;

            if ($isPrimary) {
                GoodsSupplier::forOrganization($this->context->organizationId())
                    ->where('goods_id', $data['goods_id'])
                    ->update(['is_primary' => false]);
            }

            $goodsSupplier = new GoodsSupplier();
            $goodsSupplier->organization_id = $this->context->organizationId();
            $goodsSupplier->supplier_id = $data['supplier_id'];
            $goodsSupplier->goods_id = $data['goods_id'];
            $goodsSupplier->supplier_sku = $data['supplier_sku'] ?? null;
            $goodsSupplier->purchase_price = $data['purchase_price'];
            $goodsSupplier->lead_time_days = $data['lead_time_days'] ?? 0;
            $goodsSupplier->is_primary = $isPrimary;
            $goodsSupplier->save();

            $goodsSupplier->load(['supplier', 'goods']);

            AuditService::log(
                action: 'GOODS_SUPPLIER_CREATED',
                entityType: 'GoodsSupplier',
                entityId: (string) $goodsSupplier->id,
                oldData: null,
                newData: $goodsSupplier->toArray(),
                userId: $this->context->user()->id,
                organizationId: $this->context->organizationId()
            );

            return $goodsSupplier;
        });
    }

    public function update(int $id, array $data): GoodsSupplier
    {
        $goodsSupplier = $this->getById($id);
        $oldData = $goodsSupplier->toArray();

        if (isset($data['supplier_id'])) {
            $supplier = Supplier::forOrganization($this->context->organizationId())->find($data['supplier_id']);
            if (!$supplier) {
                throw new MasterDataException('Invalid Supplier for current organization', 'CROSS_ORGANIZATION_REFERENCE', 422);
            }
            $goodsSupplier->supplier_id = $data['supplier_id'];
        }

        if (isset($data['goods_id'])) {
            $goods = Goods::forOrganization($this->context->organizationId())->find($data['goods_id']);
            if (!$goods) {
                throw new MasterDataException('Invalid Goods for current organization', 'CROSS_ORGANIZATION_REFERENCE', 422);
            }
            $goodsSupplier->goods_id = $data['goods_id'];
        }

        return DB::transaction(function () use ($goodsSupplier, $data, $oldData) {
            if (!empty($data['is_primary'])) {
                GoodsSupplier::forOrganization($this->context->organizationId())
                    ->where('goods_id', $goodsSupplier->goods_id)
                    ->where('id', '!=', $goodsSupplier->id)
                    ->update(['is_primary' => false]);
                $goodsSupplier->is_primary = true;
            } elseif (isset($data['is_primary'])) {
                $goodsSupplier->is_primary = false;
            }

            if (array_key_exists('supplier_sku', $data)) {
                $goodsSupplier->supplier_sku = $data['supplier_sku'];
            }
            if (isset($data['purchase_price'])) {
                $goodsSupplier->purchase_price = $data['purchase_price'];
            }
            if (isset($data['lead_time_days'])) {
                $goodsSupplier->lead_time_days = $data['lead_time_days'];
            }

            $goodsSupplier->save();
            $goodsSupplier->load(['supplier', 'goods']);

            AuditService::log(
                action: 'GOODS_SUPPLIER_UPDATED',
                entityType: 'GoodsSupplier',
                entityId: (string) $goodsSupplier->id,
                oldData: $oldData,
                newData: $goodsSupplier->toArray(),
                userId: $this->context->user()->id,
                organizationId: $this->context->organizationId()
            );

            return $goodsSupplier;
        });
    }

    public function delete(int $id): bool
    {
        $goodsSupplier = $this->getById($id);
        $oldData = $goodsSupplier->toArray();

        $goodsSupplier->delete();

        AuditService::log(
            action: 'GOODS_SUPPLIER_DELETED',
            entityType: 'GoodsSupplier',
            entityId: (string) $id,
            oldData: $oldData,
            newData: null,
            userId: $this->context->user()->id,
            organizationId: $this->context->organizationId()
        );

        return true;
    }
}
