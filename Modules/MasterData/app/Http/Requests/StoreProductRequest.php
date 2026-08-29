<?php

namespace Modules\MasterData\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\AuthenticationAudit\Services\OrganizationContext;

class StoreProductRequest extends MasterDataBaseRequest
{
    public function rules(): array
    {
        $orgId = app(OrganizationContext::class)->organizationId();

        return [
            'sku' => [
                'required',
                'string',
                'max:50',
                Rule::unique('products', 'sku')->where('organization_id', $orgId),
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('products', 'barcode')->where('organization_id', $orgId),
            ],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where('organization_id', $orgId),
            ],
            'brand_id' => [
                'nullable',
                'integer',
                Rule::exists('brands', 'id')->where('organization_id', $orgId),
            ],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
