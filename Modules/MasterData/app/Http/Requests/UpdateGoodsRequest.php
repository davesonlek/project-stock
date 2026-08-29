<?php

namespace Modules\MasterData\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\AuthenticationAudit\Services\OrganizationContext;

class UpdateGoodsRequest extends MasterDataBaseRequest
{
    public function rules(): array
    {
        $orgId = app(OrganizationContext::class)->organizationId();

        return [
            'product_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('products', 'id')->where('organization_id', $orgId),
            ],
            'unit_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('units', 'id')->where('organization_id', $orgId),
            ],
            'barcode' => ['nullable', 'string', 'max:100'],
            'pack_size' => ['sometimes', 'required', 'numeric', 'gt:0'],
            'cost' => ['sometimes', 'required', 'numeric', 'min:0'],
            'sell_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'is_lot_tracked' => ['nullable', 'boolean'],
            'is_serial_tracked' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
