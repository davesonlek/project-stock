<?php

namespace Modules\AuthenticationAudit\Services;

use Illuminate\Support\Facades\Request;
use Modules\AuthenticationAudit\Models\AuditLog;

class AuditService
{
    /**
     * Append-only logging to audit_logs table.
     */
    public static function log(
        string $action,
        string $entityType,
        ?string $entityId = null,
        ?array $oldData = null,
        ?array $newData = null,
        ?string $userId = null,
        ?string $organizationId = null
    ): AuditLog {
        // Sanitize sensitive fields from payloads
        $oldData = self::sanitize($oldData);
        $newData = self::sanitize($newData);

        if ($organizationId === null && app()->bound(OrganizationContext::class)) {
            $organizationId = app(OrganizationContext::class)->organizationId();
        }
        if ($userId === null && app()->bound(OrganizationContext::class)) {
            $userId = app(OrganizationContext::class)->userId();
        } elseif ($userId === null) {
            $userId = auth()->id();
        }

        return AuditLog::create([
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_data' => $oldData,
            'new_data' => $newData,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'device_id' => Request::header('X-Device-Id'),
            'created_at' => now(),
        ]);
    }

    /**
     * Ensure passwords, tokens, and secrets are stripped from audit data.
     */
    private static function sanitize(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        $sensitiveKeys = ['password', 'password_confirmation', 'token', 'access_token', 'refresh_token', 'secret', 'jwt'];

        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), $sensitiveKeys, true)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = self::sanitize($value);
            }
        }

        return $data;
    }
}
