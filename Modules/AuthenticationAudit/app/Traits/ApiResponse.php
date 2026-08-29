<?php

namespace Modules\AuthenticationAudit\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    /**
     * Standard success JSON response.
     */
    protected function successResponse(mixed $data = [], string $message = 'Success', int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
        ], $statusCode);
    }

    /**
     * Standard created JSON response.
     */
    protected function createdResponse(mixed $data = [], string $message = 'Created', int $statusCode = 201): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
        ], $statusCode);
    }

    /**
     * Standard paginated JSON response.
     */
    protected function paginatedResponse(mixed $paginator, ?string $resourceClass = null, int $statusCode = 200): JsonResponse
    {
        $items = $paginator->items();
        $data = $resourceClass ? $resourceClass::collection($items) : $items;

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], $statusCode);
    }

    /**
     * Standard error JSON response.
     */
    protected function errorResponse(string $code, string $message, array $errors = [], int $statusCode = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'code' => $code,
            'message' => $message,
            'errors' => empty($errors) ? (object) [] : $errors,
        ], $statusCode);
    }
}
