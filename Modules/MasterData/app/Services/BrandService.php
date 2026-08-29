<?php

namespace Modules\MasterData\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\AuthenticationAudit\Services\AuditService;
use Modules\AuthenticationAudit\Services\OrganizationContext;
use Modules\MasterData\Exceptions\MasterDataException;
use Modules\MasterData\Models\Brand;

class BrandService
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

        $query = Brand::forOrganization($this->context->organizationId());

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where('name', 'ilike', $search);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $sortField = in_array($filters['sort'] ?? '', ['id', 'name', 'created_at'], true) ? $filters['sort'] : 'id';
        $direction = strtolower($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sortField, $direction)->paginate($perPage);
    }

    public function getById(int $id): Brand
    {
        $brand = Brand::forOrganization($this->context->organizationId())->find($id);

        if (!$brand) {
            throw new MasterDataException('Brand not found', 'MASTER_DATA_NOT_FOUND', 404);
        }

        return $brand;
    }

    public function create(array $data): Brand
    {
        $brand = new Brand();
        $brand->organization_id = $this->context->organizationId();
        $brand->name = $data['name'];
        $brand->is_active = $data['is_active'] ?? true;
        $brand->created_by = $this->context->user()->id;
        $brand->updated_by = $this->context->user()->id;
        $brand->save();

        AuditService::log(
            action: 'BRAND_CREATED',
            entityType: 'Brand',
            entityId: (string) $brand->id,
            oldData: null,
            newData: $brand->toArray(),
            userId: $this->context->user()->id,
            organizationId: $this->context->organizationId()
        );

        return $brand;
    }

    public function update(int $id, array $data): Brand
    {
        $brand = $this->getById($id);
        $oldData = $brand->toArray();

        if (isset($data['name'])) {
            $brand->name = $data['name'];
        }
        if (isset($data['is_active'])) {
            $brand->is_active = $data['is_active'];
        }
        $brand->updated_by = $this->context->user()->id;
        $brand->save();

        AuditService::log(
            action: 'BRAND_UPDATED',
            entityType: 'Brand',
            entityId: (string) $brand->id,
            oldData: $oldData,
            newData: $brand->toArray(),
            userId: $this->context->user()->id,
            organizationId: $this->context->organizationId()
        );

        return $brand;
    }

    public function setStatus(int $id, bool $isActive): Brand
    {
        $brand = $this->getById($id);
        $oldData = $brand->toArray();

        $brand->is_active = $isActive;
        $brand->updated_by = $this->context->user()->id;
        $brand->save();

        AuditService::log(
            action: $isActive ? 'BRAND_ACTIVATED' : 'BRAND_DEACTIVATED',
            entityType: 'Brand',
            entityId: (string) $brand->id,
            oldData: $oldData,
            newData: $brand->toArray(),
            userId: $this->context->user()->id,
            organizationId: $this->context->organizationId()
        );

        return $brand;
    }

    public function delete(int $id): bool
    {
        $brand = $this->getById($id);

        if ($brand->products()->count() > 0) {
            throw new MasterDataException(
                'Cannot delete brand because it has associated products. Please deactivate it instead.',
                'RESOURCE_IN_USE',
                409
            );
        }

        $oldData = $brand->toArray();
        $brand->delete();

        AuditService::log(
            action: 'BRAND_DELETED',
            entityType: 'Brand',
            entityId: (string) $id,
            oldData: $oldData,
            newData: null,
            userId: $this->context->user()->id,
            organizationId: $this->context->organizationId()
        );

        return true;
    }
}
