<?php

namespace Modules\InventoryTransaction\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Modules\AuthenticationAudit\Services\OrganizationContext;

abstract class StockTransactionBaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function organizationId(): ?string
    {
        if (app()->bound(OrganizationContext::class)) {
            return app(OrganizationContext::class)->organizationId();
        }
        return $this->header('X-Organization-Id');
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
