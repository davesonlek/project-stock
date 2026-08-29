<?php

namespace Modules\MasterData\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\AuthenticationAudit\Services\AuditService;
use Modules\AuthenticationAudit\Services\OrganizationContext;
use Modules\MasterData\Exceptions\MasterDataException;
use Modules\MasterData\Models\Category;

class CategoryService
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

        $query = Category::forOrganization($this->context->organizationId());

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

    public function getById(int $id): Category
    {
        $category = Category::forOrganization($this->context->organizationId())->find($id);

        if (!$category) {
            throw new MasterDataException('Category not found', 'MASTER_DATA_NOT_FOUND', 404);
        }

        return $category;
    }

    public function create(array $data): Category
    {
        $category = new Category();
        $category->organization_id = $this->context->organizationId();
        $category->name = $data['name'];
        $category->is_active = $data['is_active'] ?? true;
        $category->created_by = $this->context->user()->id;
        $category->updated_by = $this->context->user()->id;
        $category->save();

        AuditService::log(
            action: 'CATEGORY_CREATED',
            entityType: 'Category',
            entityId: (string) $category->id,
            oldData: null,
            newData: $category->toArray(),
            userId: $this->context->user()->id,
            organizationId: $this->context->organizationId()
        );

        return $category;
    }

    public function update(int $id, array $data): Category
    {
        $category = $this->getById($id);
        $oldData = $category->toArray();

        if (isset($data['name'])) {
            $category->name = $data['name'];
        }
        if (isset($data['is_active'])) {
            $category->is_active = $data['is_active'];
        }
        $category->updated_by = $this->context->user()->id;
        $category->save();

        AuditService::log(
            action: 'CATEGORY_UPDATED',
            entityType: 'Category',
            entityId: (string) $category->id,
            oldData: $oldData,
            newData: $category->toArray(),
            userId: $this->context->user()->id,
            organizationId: $this->context->organizationId()
        );

        return $category;
    }

    public function setStatus(int $id, bool $isActive): Category
    {
        $category = $this->getById($id);
        $oldData = $category->toArray();

        $category->is_active = $isActive;
        $category->updated_by = $this->context->user()->id;
        $category->save();

        AuditService::log(
            action: $isActive ? 'CATEGORY_ACTIVATED' : 'CATEGORY_DEACTIVATED',
            entityType: 'Category',
            entityId: (string) $category->id,
            oldData: $oldData,
            newData: $category->toArray(),
            userId: $this->context->user()->id,
            organizationId: $this->context->organizationId()
        );

        return $category;
    }

    public function delete(int $id): bool
    {
        $category = $this->getById($id);

        if ($category->products()->count() > 0) {
            throw new MasterDataException(
                'Cannot delete category because it has associated products. Please deactivate it instead.',
                'RESOURCE_IN_USE',
                409
            );
        }

        $oldData = $category->toArray();
        $category->delete();

        AuditService::log(
            action: 'CATEGORY_DELETED',
            entityType: 'Category',
            entityId: (string) $id,
            oldData: $oldData,
            newData: null,
            userId: $this->context->user()->id,
            organizationId: $this->context->organizationId()
        );

        return true;
    }
}
