<?php

namespace Modules\MasterData\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\AuthenticationAudit\Services\OrganizationContext;

class StoreWarehouseRequest extends MasterDataBaseRequest
{
    public function rules(): array
    {
        $orgId = app(OrganizationContext::class)->organizationId();

        return [
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('warehouses', 'code')->where('organization_id', $orgId),
            ],
            'name' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'manager_id' => [
                'nullable',
                'uuid',
                Rule::exists('user_organizations', 'user_id')->where('organization_id', $orgId),
            ],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
