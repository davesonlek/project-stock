<?php

namespace Modules\MasterData\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\AuthenticationAudit\Services\OrganizationContext;
use Modules\MasterData\Models\WarehouseLocation;

class UpdateWarehouseLocationRequest extends MasterDataBaseRequest
{
    public function rules(): array
    {
        $orgId = app(OrganizationContext::class)->organizationId();
        $id = $this->route('warehouse_location') ?? $this->route('id');

        $location = WarehouseLocation::forOrganization($orgId)->find($id);
        $warehouseId = $this->input('warehouse_id') ?? $location?->warehouse_id;

        return [
            'warehouse_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('warehouses', 'id')->where('organization_id', $orgId),
            ],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:30',
                Rule::unique('warehouse_locations', 'code')
                    ->where(function ($query) use ($warehouseId) {
                        return $query->where('warehouse_id', $warehouseId);
                    })
                    ->ignore($id),
            ],
            'name' => ['nullable', 'string', 'max:100'],
            'zone' => ['nullable', 'string', 'max:30'],
            'aisle' => ['nullable', 'string', 'max:30'],
            'rack' => ['nullable', 'string', 'max:30'],
            'bin' => ['nullable', 'string', 'max:30'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
