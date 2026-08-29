<?php

namespace Modules\MasterData\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\AuthenticationAudit\Services\AuditService;
use Modules\AuthenticationAudit\Services\OrganizationContext;
use Modules\MasterData\Exceptions\MasterDataException;
use Modules\MasterData\Models\Brand;
use Modules\MasterData\Models\Category;
use Modules\MasterData\Models\Product;

class ProductService
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

        $query = Product::forOrganization($this->context->organizationId())
            ->with(['category', 'brand']);

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'ilike', $search)
                  ->orWhere('name', 'ilike', $search)
                  ->orWhere('barcode', 'ilike', $search);
            });
        }

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['brand_id'])) {
            $query->where('brand_id', $filters['brand_id']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $sortField = in_array($filters['sort'] ?? '', ['id', 'sku', 'name', 'created_at'], true) ? $filters['sort'] : 'id';
        $direction = strtolower($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sortField, $direction)->paginate($perPage);
    }

    public function getById(int $id): Product
    {
        $product = Product::forOrganization($this->context->organizationId())
            ->with(['category', 'brand'])
            ->find($id);

        if (!$product) {
            throw new MasterDataException('Product not found', 'MASTER_DATA_NOT_FOUND', 404);
        }

        return $product;
    }

    public function create(array $data): Product
    {
        // Explicit cross-tenant validation for Category and Brand
        $category = Category::forOrganization($this->context->organizationId())->find($data['category_id']);
        if (!$category) {
            throw new MasterDataException('Invalid Category for current organization', 'CROSS_ORGANIZATION_REFERENCE', 422);
        }

        if (!empty($data['brand_id'])) {
            $brand = Brand::forOrganization($this->context->organizationId())->find($data['brand_id']);
            if (!$brand) {
                throw new MasterDataException('Invalid Brand for current organization', 'CROSS_ORGANIZATION_REFERENCE', 422);
            }
        }

        $product = new Product();
        $product->organization_id = $this->context->organizationId();
        $product->sku = $data['sku'];
        $product->barcode = $data['barcode'] ?? null;
        $product->name = $data['name'];
        $product->description = $data['description'] ?? null;
        $product->category_id = $data['category_id'];
        $product->brand_id = $data['brand_id'] ?? null;
        $product->is_active = $data['is_active'] ?? true;
        $product->created_by = $this->context->user()->id;
        $product->updated_by = $this->context->user()->id;
        $product->save();

        $product->load(['category', 'brand']);

        AuditService::log(
            action: 'PRODUCT_CREATED',
            entityType: 'Product',
            entityId: (string) $product->id,
            oldData: null,
            newData: $product->toArray(),
            userId: $this->context->user()->id,
            organizationId: $this->context->organizationId()
        );

        return $product;
    }

    public function update(int $id, array $data): Product
    {
        $product = $this->getById($id);
        $oldData = $product->toArray();

        if (isset($data['category_id'])) {
            $category = Category::forOrganization($this->context->organizationId())->find($data['category_id']);
            if (!$category) {
                throw new MasterDataException('Invalid Category for current organization', 'CROSS_ORGANIZATION_REFERENCE', 422);
            }
            $product->category_id = $data['category_id'];
        }

        if (array_key_exists('brand_id', $data)) {
            if (!empty($data['brand_id'])) {
                $brand = Brand::forOrganization($this->context->organizationId())->find($data['brand_id']);
                if (!$brand) {
                    throw new MasterDataException('Invalid Brand for current organization', 'CROSS_ORGANIZATION_REFERENCE', 422);
                }
                $product->brand_id = $data['brand_id'];
            } else {
                $product->brand_id = null;
            }
        }

        if (isset($data['sku'])) {
            $product->sku = $data['sku'];
        }
        if (array_key_exists('barcode', $data)) {
            $product->barcode = $data['barcode'];
        }
        if (isset($data['name'])) {
            $product->name = $data['name'];
        }
        if (array_key_exists('description', $data)) {
            $product->description = $data['description'];
        }
        if (isset($data['is_active'])) {
            $product->is_active = $data['is_active'];
        }

        $product->updated_by = $this->context->user()->id;
        $product->save();

        $product->load(['category', 'brand']);

        AuditService::log(
            action: 'PRODUCT_UPDATED',
            entityType: 'Product',
            entityId: (string) $product->id,
            oldData: $oldData,
            newData: $product->toArray(),
            userId: $this->context->user()->id,
            organizationId: $this->context->organizationId()
        );

        return $product;
    }

    public function setStatus(int $id, bool $isActive): Product
    {
        $product = $this->getById($id);
        $oldData = $product->toArray();

        $product->is_active = $isActive;
        $product->updated_by = $this->context->user()->id;
        $product->save();

        AuditService::log(
            action: $isActive ? 'PRODUCT_ACTIVATED' : 'PRODUCT_DEACTIVATED',
            entityType: 'Product',
            entityId: (string) $product->id,
            oldData: $oldData,
            newData: $product->toArray(),
            userId: $this->context->user()->id,
            organizationId: $this->context->organizationId()
        );

        return $product;
    }

    public function delete(int $id): bool
    {
        $product = $this->getById($id);

        if ($product->goods()->count() > 0) {
            throw new MasterDataException(
                'Cannot delete product because it has associated goods. Please deactivate it instead.',
                'RESOURCE_IN_USE',
                409
            );
        }

        $oldData = $product->toArray();
        $product->delete();

        AuditService::log(
            action: 'PRODUCT_DELETED',
            entityType: 'Product',
            entityId: (string) $id,
            oldData: $oldData,
            newData: null,
            userId: $this->context->user()->id,
            organizationId: $this->context->organizationId()
        );

        return true;
    }
}
