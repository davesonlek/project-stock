<?php

namespace Modules\InventoryTransaction\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\InventoryTransaction\Enums\StockDocumentStatus;
use Modules\InventoryTransaction\Enums\StockDocumentType;

class QueryStockDocumentRequest extends StockTransactionBaseRequest
{
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'document_type' => ['nullable', Rule::enum(StockDocumentType::class)],
            'status' => ['nullable', Rule::enum(StockDocumentStatus::class)],
            'created_by' => ['nullable', 'uuid'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'warehouse_id' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort' => ['nullable', 'string', 'in:document_no,document_type,status,created_at,submitted_at,approved_at'],
            'direction' => ['nullable', 'string', 'in:asc,desc,ASC,DESC'],
        ];
    }
}
