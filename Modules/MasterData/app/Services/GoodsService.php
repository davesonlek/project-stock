<?php

namespace Modules\MasterData\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\AuthenticationAudit\Services\AuditService;
use Modules\AuthenticationAudit\Services\OrganizationContext;
use Modules\MasterData\Exceptions\MasterDataException;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Product;
use Modules\MasterData\Models\Unit;

class GoodsService
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

        $query = Goods::forOrganization($this->context->organizationId())
            ->with(['product', 'unit']);

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where('barcode', 'ilike', $search);
        }

        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (!empty($filters['unit_id'])) {
            $query->where('unit_id', $filters['unit_id']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $sortField = in_array($filters['sort'] ?? '', ['id', 'barcode', 'pack_size', 'cost', 'sell_price', 'created_at'], true) ? $filters['sort'] : 'id';
        $direction = strtolower($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sortField, $direction)->paginate($perPage);
    }

    public function getById(int $id): Goods
    {
        $goods = Goods::forOrganization($this->context->organizationId())
            ->with(['product', 'unit'])
            ->find($id);

        if (!$goods) {
            throw new MasterDataException('Goods not found', 'MASTER_DATA_NOT_FOUND', 404);
        }

        return $goods;
    }

    public function create(array $data): Goods
    {
        // Cross-tenant validation
        $product = Product::forOrganization($this->context->organizationId())->find($data['product_id']);
        if (!$product) {
            throw new MasterDataException('Invalid Product for current organization', 'CROSS_ORGANIZATION_REFERENCE', 422);
        }

        $unit = Unit::forOrganization($this->context->organizationId())->find($data['unit_id']);
        if (!$unit) {
            throw new MasterDataException('Invalid Unit for current organization', 'CROSS_ORGANIZATION_REFERENCE', 422);
        }

        $goods = new Goods();
        $goods->organization_id = $this->context->organizationId();
        $goods->product_id = $data['product_id'];
        $goods->unit_id = $data['unit_id'];
        $goods->barcode = $data['barcode'] ?? null;
        $goods->pack_size = $data['pack_size'];
        $goods->cost = $data['cost'];
        $goods->sell_price = $data['sell_price'];
        $goods->is_lot_tracked = $data['is_lot_tracked'] ?? false;
        $goods->is_serial_tracked = $data['is_serial_tracked'] ?? false;
        $goods->is_active = $data['is_active'] ?? true;
        $goods->created_by = $this->context->user()->id;
        $goods->updated_by = $this->context->user()->id;
        $goods->save();

        $goods->load(['product', 'unit']);

        AuditService::log(
            action: 'GOODS_CREATED',
            entityType: 'Goods',
            entityId: (string) $goods->id,
            oldData: null,
            newData: $goods->toArray(),
            userId: $this->context->user()->id,
            organizationId: $this->context->organizationId()
        );

        return $goods;
    }

    public function update(int $id, array $data): Goods
    {
        $goods = $this->getById($id);
        $oldData = $goods->toArray();

        if (isset($data['product_id'])) {
            $product = Product::forOrganization($this->context->organizationId())->find($data['product_id']);
            if (!$product) {
                throw new MasterDataException('Invalid Product for current organization', 'CROSS_ORGANIZATION_REFERENCE', 422);
            }
            $goods->product_id = $data['product_id'];
        }

        if (isset($data['unit_id'])) {
            $unit = Unit::forOrganization($this->context->organizationId())->find($data['unit_id']);
            if (!$unit) {
                throw new MasterDataException('Invalid Unit for current organization', 'CROSS_ORGANIZATION_REFERENCE', 422);
            }
            $goods->unit_id = $data['unit_id'];
        }

        // TODO: In stock transaction step, if goods already has stock movements in ledger, prevent modifying is_lot_tracked / is_serial_tracked.

        if (array_key_exists('barcode', $data)) {
            $goods->barcode = $data['barcode'];
        }
        if (isset($data['pack_size'])) {
            $goods->pack_size = $data['pack_size'];
        }
        if (isset($data['cost'])) {
            $goods->cost = $data['cost'];
        }
        if (isset($data['sell_price'])) {
            $goods->sell_price = $data['sell_price'];
        }
        if (isset($data['is_lot_tracked'])) {
            $goods->is_lot_tracked = $data['is_lot_tracked'];
        }
        if (isset($data['is_serial_tracked'])) {
            $goods->is_serial_tracked = $data['is_serial_tracked'];
        }
        if (isset($data['is_active'])) {
            $goods->is_active = $data['is_active'];
        }

        $goods->updated_by = $this->context->user()->id;
        $goods->save();

        $goods->load(['product', 'unit']);

        AuditService::log(
            action: 'GOODS_UPDATED',
            entityType: 'Goods',
            entityId: (string) $goods->id,
            oldData: $oldData,
            newData: $goods->toArray(),
            userId: $this->context->user()->id,
            organizationId: $this->context->organizationId()
        );

        return $goods;
    }

    public function setStatus(int $id, bool $isActive): Goods
    {
        $goods = $this->getById($id);
        $oldData = $goods->toArray();

        $goods->is_active = $isActive;
        $goods->updated_by = $this->context->user()->id;
        $goods->save();

        AuditService::log(
            action: $isActive ? 'GOODS_ACTIVATED' : 'GOODS_DEACTIVATED',
            entityType: 'Goods',
            entityId: (string) $goods->id,
            oldData: $oldData,
            newData: $goods->toArray(),
            userId: $this->context->user()->id,
            organizationId: $this->context->organizationId()
        );

        return $goods;
    }

    public function delete(int $id): bool
    {
        $goods = $this->getById($id);

        if ($goods->goodsSuppliers()->count() > 0) {
            throw new MasterDataException(
                'Cannot delete goods because it has associated supplier relations. Please deactivate it instead.',
                'RESOURCE_IN_USE',
                409
            );
        }

        $oldData = $goods->toArray();
        $goods->delete();

        AuditService::log(
            action: 'GOODS_DELETED',
            entityType: 'Goods',
            entityId: (string) $id,
            oldData: $oldData,
            newData: null,
            userId: $this->context->user()->id,
            organizationId: $this->context->organizationId()
        );

        return true;
    }
}
