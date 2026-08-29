<?php

namespace Modules\AuthenticationAudit\Services;

use Tymon\JWTAuth\Facades\JWTAuth;

class LogoutService
{
    /**
     * Invalidate token and log logout action.
     */
    public function execute(): array
    {
        $user = auth('api')->user();

        if ($user) {
            AuditService::log(
                action: 'LOGOUT',
                entityType: 'Authentication',
                entityId: $user->id,
                oldData: null,
                newData: ['email' => $user->email],
                userId: $user->id
            );
        }

        JWTAuth::invalidate(JWTAuth::getToken());

        return [
            'success' => true,
            'message' => 'Successfully logged out',
            'status_code' => 200,
        ];
    }
}
