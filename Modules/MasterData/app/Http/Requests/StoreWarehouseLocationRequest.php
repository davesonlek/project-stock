<?php

namespace Modules\MasterData\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\AuthenticationAudit\Services\OrganizationContext;

class StoreWarehouseLocationRequest extends MasterDataBaseRequest
{
    public function rules(): array
    {
        $orgId = app(OrganizationContext::class)->organizationId();
        $warehouseId = $this->input('warehouse_id') ?? $this->route('warehouse');

        return [
            'warehouse_id' => [
                $this->route('warehouse') ? 'nullable' : 'required',
                'integer',
                Rule::exists('warehouses', 'id')->where('organization_id', $orgId),
            ],
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('warehouse_locations', 'code')->where(function ($query) use ($warehouseId) {
                    return $query->where('warehouse_id', $warehouseId);
                }),
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
