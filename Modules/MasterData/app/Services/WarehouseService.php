<?php

namespace Modules\MasterData\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\AuthenticationAudit\Models\UserOrganization;
use Modules\AuthenticationAudit\Services\AuditService;
use Modules\AuthenticationAudit\Services\OrganizationContext;
use Modules\MasterData\Exceptions\MasterDataException;
use Modules\MasterData\Models\Warehouse;

class WarehouseService
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

        $query = Warehouse::forOrganization($this->context->organizationId())
            ->with('manager');

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('code', 'ilike', $search)
                  ->orWhere('name', 'ilike', $search);
            });
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $sortField = in_array($filters['sort'] ?? '', ['id', 'code', 'name', 'created_at'], true) ? $filters['sort'] : 'id';
        $direction = strtolower($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sortField, $direction)->paginate($perPage);
    }

    public function getById(int $id): Warehouse
    {
        $warehouse = Warehouse::forOrganization($this->context->organizationId())
            ->with('manager')
            ->find($id);

        if (!$warehouse) {
            throw new MasterDataException('Warehouse not found', 'MASTER_DATA_NOT_FOUND', 404);
        }

        return $warehouse;
    }

    public function create(array $data): Warehouse
    {
        if (!empty($data['manager_id'])) {
            $isMember = UserOrganization::where('organization_id', $this->context->organizationId())
                ->where('user_id', $data['manager_id'])
                ->exists();

            if (!$isMember) {
                throw new MasterDataException('Manager must be a member of current organization', 'INVALID_WAREHOUSE_MANAGER', 422);
            }
        }

        $warehouse = new Warehouse();
        $warehouse->organization_id = $this->context->organizationId();
        $warehouse->code = $data['code'];
        $warehouse->name = $data['name'];
        $warehouse->address = $data['address'] ?? null;
        $warehouse->manager_id = $data['manager_id'] ?? null;
        $warehouse->is_active = $data['is_active'] ?? true;
        $warehouse->created_by = $this->context->user()->id;
        $warehouse->updated_by = $this->context->user()->id;
        $warehouse->save();

        $warehouse->load('manager');

        AuditService::log(
            action: 'WAREHOUSE_CREATED',
            entityType: 'Warehouse',
            entityId: (string) $warehouse->id,
            oldData: null,
            newData: $warehouse->toArray(),
            userId: $this->context->user()->id,
            organizationId: $this->context->organizationId()
        );

        return $warehouse;
    }

    public function update(int $id, array $data): Warehouse
    {
        $warehouse = $this->getById($id);
        $oldData = $warehouse->toArray();

        if (array_key_exists('manager_id', $data)) {
            if (!empty($data['manager_id'])) {
                $isMember = UserOrganization::where('organization_id', $this->context->organizationId())
                    ->where('user_id', $data['manager_id'])
                    ->exists();

                if (!$isMember) {
                    throw new MasterDataException('Manager must be a member of current organization', 'INVALID_WAREHOUSE_MANAGER', 422);
                }
                $warehouse->manager_id = $data['manager_id'];
            } else {
                $warehouse->manager_id = null;
            }
        }

        if (isset($data['code'])) {
            $warehouse->code = $data['code'];
        }
        if (isset($data['name'])) {
            $warehouse->name = $data['name'];
        }
        if (array_key_exists('address', $data)) {
            $warehouse->address = $data['address'];
        }
        if (isset($data['is_active'])) {
            $warehouse->is_active = $data['is_active'];
        }

        $warehouse->updated_by = $this->context->user()->id;
        $warehouse->save();

        $warehouse->load('manager');

        AuditService::log(
            action: 'WAREHOUSE_UPDATED',
            entityType: 'Warehouse',
            entityId: (string) $warehouse->id,
            oldData: $oldData,
            newData: $warehouse->toArray(),
            userId: $this->context->user()->id,
            organizationId: $this->context->organizationId()
        );

        return $warehouse;
    }

    public function setStatus(int $id, bool $isActive): Warehouse
    {
        $warehouse = $this->getById($id);
        $oldData = $warehouse->toArray();

        $warehouse->is_active = $isActive;
        $warehouse->updated_by = $this->context->user()->id;
        $warehouse->save();

        AuditService::log(
            action: $isActive ? 'WAREHOUSE_ACTIVATED' : 'WAREHOUSE_DEACTIVATED',
            entityType: 'Warehouse',
            entityId: (string) $warehouse->id,
            oldData: $oldData,
            newData: $warehouse->toArray(),
            userId: $this->context->user()->id,
            organizationId: $this->context->organizationId()
        );

        return $warehouse;
    }

    public function delete(int $id): bool
    {
        $warehouse = $this->getById($id);

        if ($warehouse->locations()->count() > 0) {
            throw new MasterDataException(
                'Cannot delete warehouse because it has associated locations. Please deactivate it instead.',
                'RESOURCE_IN_USE',
                409
            );
        }

        $oldData = $warehouse->toArray();
        $warehouse->delete();

        AuditService::log(
            action: 'WAREHOUSE_DELETED',
            entityType: 'Warehouse',
            entityId: (string) $id,
            oldData: $oldData,
            newData: null,
            userId: $this->context->user()->id,
            organizationId: $this->context->organizationId()
        );

        return true;
    }
}
