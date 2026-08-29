<?php

namespace Modules\MasterData\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\AuthenticationAudit\Services\AuditService;
use Modules\AuthenticationAudit\Services\OrganizationContext;
use Modules\MasterData\Exceptions\MasterDataException;
use Modules\MasterData\Models\Unit;

class UnitService
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

        $query = Unit::forOrganization($this->context->organizationId());

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

    public function getById(int $id): Unit
    {
        $unit = Unit::forOrganization($this->context->organizationId())->find($id);

        if (!$unit) {
            throw new MasterDataException('Unit not found', 'MASTER_DATA_NOT_FOUND', 404);
        }

        return $unit;
    }

    public function create(array $data): Unit
    {
        $unit = new Unit();
        $unit->organization_id = $this->context->organizationId();
        $unit->code = $data['code'];
        $unit->name = $data['name'];
        $unit->is_active = $data['is_active'] ?? true;
        $unit->created_by = $this->context->user()->id;
        $unit->updated_by = $this->context->user()->id;
        $unit->save();

        AuditService::log(
            action: 'UNIT_CREATED',
            entityType: 'Unit',
            entityId: (string) $unit->id,
            oldData: null,
            newData: $unit->toArray(),
            userId: $this->context->user()->id,
            organizationId: $this->context->organizationId()
        );

        return $unit;
    }

    public function update(int $id, array $data): Unit
    {
        $unit = $this->getById($id);
        $oldData = $unit->toArray();

        if (isset($data['code'])) {
            $unit->code = $data['code'];
        }
        if (isset($data['name'])) {
            $unit->name = $data['name'];
        }
        if (isset($data['is_active'])) {
            $unit->is_active = $data['is_active'];
        }
        $unit->updated_by = $this->context->user()->id;
        $unit->save();

        AuditService::log(
            action: 'UNIT_UPDATED',
            entityType: 'Unit',
            entityId: (string) $unit->id,
            oldData: $oldData,
            newData: $unit->toArray(),
            userId: $this->context->user()->id,
            organizationId: $this->context->organizationId()
        );

        return $unit;
    }

    public function setStatus(int $id, bool $isActive): Unit
    {
        $unit = $this->getById($id);
        $oldData = $unit->toArray();

        $unit->is_active = $isActive;
        $unit->updated_by = $this->context->user()->id;
        $unit->save();

        AuditService::log(
            action: $isActive ? 'UNIT_ACTIVATED' : 'UNIT_DEACTIVATED',
            entityType: 'Unit',
            entityId: (string) $unit->id,
            oldData: $oldData,
            newData: $unit->toArray(),
            userId: $this->context->user()->id,
            organizationId: $this->context->organizationId()
        );

        return $unit;
    }

    public function delete(int $id): bool
    {
        $unit = $this->getById($id);

        if ($unit->goods()->count() > 0) {
            throw new MasterDataException(
                'Cannot delete unit because it has associated goods. Please deactivate it instead.',
                'RESOURCE_IN_USE',
                409
            );
        }

        $oldData = $unit->toArray();
        $unit->delete();

        AuditService::log(
            action: 'UNIT_DELETED',
            entityType: 'Unit',
            entityId: (string) $id,
            oldData: $oldData,
            newData: null,
            userId: $this->context->user()->id,
            organizationId: $this->context->organizationId()
        );

        return true;
    }
}
