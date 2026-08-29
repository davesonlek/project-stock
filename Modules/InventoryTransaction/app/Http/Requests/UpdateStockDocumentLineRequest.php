<?php

namespace Modules\InventoryTransaction\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateStockDocumentLineRequest extends StockTransactionBaseRequest
{
    public function rules(): array
    {
        $orgId = $this->organizationId();

        return [
            'goods_id' => ['sometimes', 'required', 'integer', Rule::exists('goods', 'id')->where('organization_id', $orgId)],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'counted_quantity' => ['nullable', 'numeric', 'min:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'lot_id' => ['nullable', 'integer'],
            'lot_no' => ['nullable', 'string', 'max:100'],
            'manufactured_at' => ['nullable', 'date'],
            'expired_at' => ['nullable', 'date', 'after_or_equal:manufactured_at'],
            'serials' => ['nullable', 'array'],
            'serials.*' => ['required', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
