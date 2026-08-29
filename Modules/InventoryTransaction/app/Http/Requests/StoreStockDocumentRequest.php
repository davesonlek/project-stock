<?php

namespace Modules\InventoryTransaction\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\InventoryTransaction\Enums\StockDocumentType;

class StoreStockDocumentRequest extends StockTransactionBaseRequest
{
    public function rules(): array
    {
        $orgId = $this->organizationId();

        return [
            'document_type' => ['required', Rule::enum(StockDocumentType::class)],
            'source_warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')->where('organization_id', $orgId)],
            'source_location_id' => ['nullable', 'integer', 'exists:warehouse_locations,id'],
            'destination_warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')->where('organization_id', $orgId)],
            'destination_location_id' => ['nullable', 'integer', 'exists:warehouse_locations,id'],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->where('organization_id', $orgId)],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
