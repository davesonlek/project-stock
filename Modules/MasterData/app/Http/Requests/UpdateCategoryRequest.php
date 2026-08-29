<?php

namespace Modules\MasterData\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\AuthenticationAudit\Services\OrganizationContext;

class UpdateCategoryRequest extends MasterDataBaseRequest
{
    public function rules(): array
    {
        $orgId = app(OrganizationContext::class)->organizationId();
        $id = $this->route('category') ?? $this->route('id');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('categories', 'name')
                    ->where('organization_id', $orgId)
                    ->ignore($id),
            ],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
