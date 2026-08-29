<?php

namespace Modules\MasterData\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\AuthenticationAudit\Services\AuditService;
use Modules\AuthenticationAudit\Services\OrganizationContext;
use Modules\MasterData\Exceptions\MasterDataException;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;

class WarehouseLocationService
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

        $query = WarehouseLocation::forOrganization($this->context->organizationId())
            ->with('warehouse');

        if (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('code', 'ilike', $search)
                  ->orWhere('name', 'ilike', $search)
                  ->orWhere('zone', 'ilike', $search);
            });
        }

        if (!empty($filters['zone'])) {
            $query->where('zone', $filters['zone']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $sortField = in_array($filters['sort'] ?? '', ['id', 'code', 'name', 'zone', 'created_at'], true) ? $filters['sort'] : 'id';
        $direction = strtolower($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sortField, $direction)->paginate($perPage);
    }

    public function getById(int $id): WarehouseLocation
    {
        $location = WarehouseLocation::forOrganization($this->context->organizationId())
            ->with('warehouse')
            ->find($id);

        if (!$location) {
            throw new MasterDataException('Warehouse location not found', 'MASTER_DATA_NOT_FOUND', 404);
        }

        return $location;
    }

    public function create(array $data): WarehouseLocation
    {
        $warehouse = Warehouse::forOrganization($this->context->organizationId())->find($data['warehouse_id']);
        if (!$warehouse) {
            throw new MasterDataException('Invalid Warehouse for current organization', 'CROSS_ORGANIZATION_REFERENCE', 422);
        }

        $location = new WarehouseLocation();
        $location->organization_id = $this->context->organizationId();
        $location->warehouse_id = $data['warehouse_id'];
        $location->code = $data['code'];
        $location->name = $data['name'] ?? null;
        $location->zone = $data['zone'] ?? null;
        $location->aisle = $data['aisle'] ?? null;
        $location->rack = $data['rack'] ?? null;
        $location->bin = $data['bin'] ?? null;
        $location->is_active = $data['is_active'] ?? true;
        $location->save();

        $location->load('warehouse');

        AuditService::log(
            action: 'WAREHOUSE_LOCATION_CREATED',
            entityType: 'WarehouseLocation',
            entityId: (string) $location->id,
            oldData: null,
            newData: $location->toArray(),
            userId: $this->context->user()->id,
            organizationId: $this->context->organizationId()
        );

        return $location;
    }

    public function update(int $id, array $data): WarehouseLocation
    {
        $location = $this->getById($id);
        $oldData = $location->toArray();

        if (isset($data['warehouse_id'])) {
            $warehouse = Warehouse::forOrganization($this->context->organizationId())->find($data['warehouse_id']);
            if (!$warehouse) {
                throw new MasterDataException('Invalid Warehouse for current organization', 'CROSS_ORGANIZATION_REFERENCE', 422);
            }
            $location->warehouse_id = $data['warehouse_id'];
        }

        if (isset($data['code'])) {
            $location->code = $data['code'];
        }
        if (array_key_exists('name', $data)) {
            $location->name = $data['name'];
        }
        if (array_key_exists('zone', $data)) {
            $location->zone = $data['zone'];
        }
        if (array_key_exists('aisle', $data)) {
            $location->aisle = $data['aisle'];
        }
        if (array_key_exists('rack', $data)) {
            $location->rack = $data['rack'];
        }
        if (array_key_exists('bin', $data)) {
            $location->bin = $data['bin'];
        }
        if (isset($data['is_active'])) {
            $location->is_active = $data['is_active'];
        }

        $location->save();

        $location->load('warehouse');

        AuditService::log(
            action: 'WAREHOUSE_LOCATION_UPDATED',
            entityType: 'WarehouseLocation',
            entityId: (string) $location->id,
            oldData: $oldData,
            newData: $location->toArray(),
            userId: $this->context->user()->id,
            organizationId: $this->context->organizationId()
        );

        return $location;
    }

    public function setStatus(int $id, bool $isActive): WarehouseLocation
    {
        $location = $this->getById($id);
        $oldData = $location->toArray();

        $location->is_active = $isActive;
        $location->updated_by = $this->context->user()->id;
        $location->save();

        AuditService::log(
            action: $isActive ? 'WAREHOUSE_LOCATION_ACTIVATED' : 'WAREHOUSE_LOCATION_DEACTIVATED',
            entityType: 'WarehouseLocation',
            entityId: (string) $location->id,
            oldData: $oldData,
            newData: $location->toArray(),
            userId: $this->context->user()->id,
            organizationId: $this->context->organizationId()
        );

        return $location;
    }

    public function delete(int $id): bool
    {
        $location = $this->getById($id);
        $oldData = $location->toArray();

        $location->delete();

        AuditService::log(
            action: 'WAREHOUSE_LOCATION_DELETED',
            entityType: 'WarehouseLocation',
            entityId: (string) $id,
            oldData: $oldData,
            newData: null,
            userId: $this->context->user()->id,
            organizationId: $this->context->organizationId()
        );

        return true;
    }
}
