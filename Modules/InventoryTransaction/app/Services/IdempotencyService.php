<?php

namespace Modules\InventoryTransaction\Services;

use Illuminate\Support\Str;
use Modules\InventoryTransaction\Enums\IdempotencyStatus;
use Modules\InventoryTransaction\Exceptions\StockDocumentException;
use Modules\InventoryTransaction\Models\IdempotencyKey;

class IdempotencyService
{
    /**
     * Compute SHA-256 hash of request context.
     */
    public function computeHash(string $httpMethod, string $routeName, string $documentId, array $payload = []): string
    {
        ksort($payload);
        $normalizedPayload = json_encode($payload);
        return hash('sha256', strtoupper($httpMethod) . '|' . $routeName . '|' . $documentId . '|' . $normalizedPayload);
    }

    /**
     * Check if a completed idempotency key exists or acquire a new processing lock row.
     */
    public function acquire(
        string $orgId,
        string $key,
        string $httpMethod,
        string $routeName,
        string $documentId,
        array $payload = []
    ): ?IdempotencyKey {
        $hash = $this->computeHash($httpMethod, $routeName, $documentId, $payload);

        // Check for existing record (without lock first to see if COMPLETED)
        $existing = IdempotencyKey::where('organization_id', $orgId)
            ->where('route_name', $routeName)
            ->where('key', $key)
            ->first();

        if ($existing) {
            if ($existing->request_hash !== $hash) {
                throw new StockDocumentException(
                    'Idempotency key has already been used for a different request',
                    'IDEMPOTENCY_KEY_CONFLICT',
                    409
                );
            }

            if ($existing->status === IdempotencyStatus::COMPLETED || $existing->status === 'COMPLETED') {
                return $existing; // Cached result available
            }

            if ($existing->status === IdempotencyStatus::PROCESSING || $existing->status === 'PROCESSING') {
                // If it is currently processing, lock the row for update to serialize or detect conflict
                $locked = IdempotencyKey::where('id', $existing->id)->lockForUpdate()->first();
                if ($locked && ($locked->status === IdempotencyStatus::COMPLETED || $locked->status === 'COMPLETED')) {
                    return $locked;
                }
                throw new StockDocumentException(
                    'A concurrent request with the same idempotency key is currently processing',
                    'IDEMPOTENCY_KEY_CONFLICT',
                    409
                );
            }
        }

        // Insert new processing record
        try {
            return IdempotencyKey::create([
                'id' => (string) Str::uuid(),
                'organization_id' => $orgId,
                'key' => $key,
                'http_method' => strtoupper($httpMethod),
                'route_name' => $routeName,
                'request_hash' => $hash,
                'status' => IdempotencyStatus::PROCESSING,
                'created_at' => now(),
                'expires_at' => now()->addHours(24),
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Concurrent insert caught by PostgreSQL unique constraint
            $existing = IdempotencyKey::where('organization_id', $orgId)
                ->where('route_name', $routeName)
                ->where('key', $key)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ($existing->request_hash !== $hash) {
                    throw new StockDocumentException(
                        'Idempotency key has already been used for a different request',
                        'IDEMPOTENCY_KEY_CONFLICT',
                        409
                    );
                }
                if ($existing->status === IdempotencyStatus::COMPLETED || $existing->status === 'COMPLETED') {
                    return $existing;
                }
            }

            throw new StockDocumentException(
                'Concurrent request with the same idempotency key encountered',
                'IDEMPOTENCY_KEY_CONFLICT',
                409
            );
        }
    }

    /**
     * Mark the idempotency key as completed with response data.
     */
    public function complete(IdempotencyKey $idempotencyKey, int $responseCode, array $responseData): void
    {
        $idempotencyKey->update([
            'status' => IdempotencyStatus::COMPLETED,
            'response_code' => $responseCode,
            'response_data' => $responseData,
            'completed_at' => now(),
        ]);
    }
}
