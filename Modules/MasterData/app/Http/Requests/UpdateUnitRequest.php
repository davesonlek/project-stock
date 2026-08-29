<?php

namespace Modules\MasterData\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\AuthenticationAudit\Services\OrganizationContext;

class UpdateUnitRequest extends MasterDataBaseRequest
{
    public function rules(): array
    {
        $orgId = app(OrganizationContext::class)->organizationId();
        $id = $this->route('unit') ?? $this->route('id');

        return [
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('units', 'code')
                    ->where('organization_id', $orgId)
                    ->ignore($id),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
