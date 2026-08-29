<?php

namespace Modules\InventoryTransaction\Http\Requests;

class CancelStockDocumentRequest extends StockTransactionBaseRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:1', 'max:1000'],
        ];
    }
}
