<?php

namespace Modules\MasterData\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\AuthenticationAudit\Services\OrganizationContext;

class UpdateBrandRequest extends MasterDataBaseRequest
{
    public function rules(): array
    {
        $orgId = app(OrganizationContext::class)->organizationId();
        $id = $this->route('brand') ?? $this->route('id');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('brands', 'name')
                    ->where('organization_id', $orgId)
                    ->ignore($id),
            ],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
