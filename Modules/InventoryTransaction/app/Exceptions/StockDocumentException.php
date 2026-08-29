<?php

namespace Modules\InventoryTransaction\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class StockDocumentException extends Exception
{
    protected string $errorCode;
    protected int $statusCode;
    protected array $errors;

    public function __construct(string $message, string $errorCode = 'STOCK_DOCUMENT_ERROR', int $statusCode = 400, array $errors = [])
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
        $this->statusCode = $statusCode;
        $this->errors = $errors;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'code' => $this->errorCode,
            'message' => $this->getMessage(),
            'errors' => empty($this->errors) ? (object) [] : $this->errors,
        ], $this->statusCode);
    }
}
