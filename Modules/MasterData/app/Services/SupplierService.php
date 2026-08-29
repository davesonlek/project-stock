<?php

namespace Modules\MasterData\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\AuthenticationAudit\Services\AuditService;
use Modules\AuthenticationAudit\Services\OrganizationContext;
use Modules\MasterData\Exceptions\MasterDataException;
use Modules\MasterData\Models\Supplier;

class SupplierService
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

        $query = Supplier::forOrganization($this->context->organizationId());

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', $search)
                  ->orWhere('tax_id', 'ilike', $search)
                  ->orWhere('email', 'ilike', $search)
                  ->orWhere('phone', 'ilike', $search);
            });
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $sortField = in_array($filters['sort'] ?? '', ['id', 'name', 'tax_id', 'created_at'], true) ? $filters['sort'] : 'id';
        $direction = strtolower($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sortField, $direction)->paginate($perPage);
    }

    public function getById(int $id): Supplier
    {
        $supplier = Supplier::forOrganization($this->context->organizationId())->find($id);

        if (!$supplier) {
            throw new MasterDataException('Supplier not found', 'MASTER_DATA_NOT_FOUND', 404);
        }

        return $supplier;
    }

    public function create(array $data): Supplier
    {
        $supplier = new Supplier();
        $supplier->organization_id = $this->context->organizationId();
        $supplier->name = $data['name'];
        $supplier->contact_person = $data['contact_person'] ?? null;
        $supplier->email = $data['email'] ?? null;
        $supplier->phone = $data['phone'] ?? null;
        $supplier->tax_id = $data['tax_id'] ?? null;
        $supplier->address = $data['address'] ?? null;
        $supplier->is_active = $data['is_active'] ?? true;
        $supplier->created_by = $this->context->user()->id;
        $supplier->updated_by = $this->context->user()->id;
        $supplier->save();

        AuditService::log(
            action: 'SUPPLIER_CREATED',
            entityType: 'Supplier',
            entityId: (string) $supplier->id,
            oldData: null,
            newData: $supplier->toArray(),
            userId: $this->context->user()->id,
            organizationId: $this->context->organizationId()
        );

        return $supplier;
    }

    public function update(int $id, array $data): Supplier
    {
        $supplier = $this->getById($id);
        $oldData = $supplier->toArray();

        if (isset($data['name'])) {
            $supplier->name = $data['name'];
        }
        if (array_key_exists('contact_person', $data)) {
            $supplier->contact_person = $data['contact_person'];
        }
        if (array_key_exists('email', $data)) {
            $supplier->email = $data['email'];
        }
        if (array_key_exists('phone', $data)) {
            $supplier->phone = $data['phone'];
        }
        if (array_key_exists('tax_id', $data)) {
            $supplier->tax_id = $data['tax_id'];
        }
        if (array_key_exists('address', $data)) {
            $supplier->address = $data['address'];
        }
        if (isset($data['is_active'])) {
            $supplier->is_active = $data['is_active'];
        }

        $supplier->updated_by = $this->context->user()->id;
        $supplier->save();

        AuditService::log(
            action: 'SUPPLIER_UPDATED',
            entityType: 'Supplier',
            entityId: (string) $supplier->id,
            oldData: $oldData,
            newData: $supplier->toArray(),
            userId: $this->context->user()->id,
            organizationId: $this->context->organizationId()
        );

        return $supplier;
    }

    public function setStatus(int $id, bool $isActive): Supplier
    {
        $supplier = $this->getById($id);
        $oldData = $supplier->toArray();

        $supplier->is_active = $isActive;
        $supplier->updated_by = $this->context->user()->id;
        $supplier->save();

        AuditService::log(
            action: $isActive ? 'SUPPLIER_ACTIVATED' : 'SUPPLIER_DEACTIVATED',
            entityType: 'Supplier',
            entityId: (string) $supplier->id,
            oldData: $oldData,
            newData: $supplier->toArray(),
            userId: $this->context->user()->id,
            organizationId: $this->context->organizationId()
        );

        return $supplier;
    }

    public function delete(int $id): bool
    {
        $supplier = $this->getById($id);

        if ($supplier->goodsSuppliers()->count() > 0) {
            throw new MasterDataException(
                'Cannot delete supplier because it has associated goods relations. Please deactivate it instead.',
                'RESOURCE_IN_USE',
                409
            );
        }

        $oldData = $supplier->toArray();
        $supplier->delete();

        AuditService::log(
            action: 'SUPPLIER_DELETED',
            entityType: 'Supplier',
            entityId: (string) $id,
            oldData: $oldData,
            newData: null,
            userId: $this->context->user()->id,
            organizationId: $this->context->organizationId()
        );

        return true;
    }
}
