<?php

namespace Modules\MasterData\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\AuthenticationAudit\Services\OrganizationContext;

class UpdateWarehouseRequest extends MasterDataBaseRequest
{
    public function rules(): array
    {
        $orgId = app(OrganizationContext::class)->organizationId();
        $id = $this->route('warehouse') ?? $this->route('id');

        return [
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:30',
                Rule::unique('warehouses', 'code')
                    ->where('organization_id', $orgId)
                    ->ignore($id),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:100'],
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
