<?php

namespace Modules\InventoryTransaction\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ReverseStockDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $idempotencyKey = $this->header('Idempotency-Key');
        if (empty($idempotencyKey)) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'code' => 'IDEMPOTENCY_KEY_REQUIRED',
                'message' => 'The Idempotency-Key header is required for reversing documents',
                'errors' => ['Idempotency-Key' => ['The Idempotency-Key header is required.']],
            ], 422));
        }
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation error',
            'code' => 'VALIDATION_ERROR',
            'errors' => $validator->errors(),
        ], 422));
    }
}
