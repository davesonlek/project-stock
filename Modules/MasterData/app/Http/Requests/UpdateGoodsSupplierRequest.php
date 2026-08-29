<?php

namespace Modules\MasterData\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\AuthenticationAudit\Services\OrganizationContext;

class UpdateGoodsSupplierRequest extends MasterDataBaseRequest
{
    public function rules(): array
    {
        $orgId = app(OrganizationContext::class)->organizationId();

        return [
            'supplier_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('suppliers', 'id')->where('organization_id', $orgId),
            ],
            'goods_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('goods', 'id')->where('organization_id', $orgId),
            ],
            'supplier_sku' => ['nullable', 'string', 'max:100'],
            'purchase_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'lead_time_days' => ['sometimes', 'required', 'integer', 'min:0'],
            'is_primary' => ['nullable', 'boolean'],
        ];
    }
}
