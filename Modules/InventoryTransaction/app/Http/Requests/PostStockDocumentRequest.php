<?php

namespace Modules\InventoryTransaction\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class PostStockDocumentRequest extends StockTransactionBaseRequest
{
    public function validationData(): array
    {
        return array_merge($this->all(), [
            'idempotency_key' => $this->header('Idempotency-Key'),
        ]);
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'max:100'],
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        $errors = $validator->errors();
        $code = $errors->has('idempotency_key') ? 'IDEMPOTENCY_KEY_REQUIRED' : 'VALIDATION_ERROR';

        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'code' => $code,
                'message' => 'Idempotency-Key header is required for stock posting.',
                'errors' => $errors,
            ], 422)
        );
    }
}
