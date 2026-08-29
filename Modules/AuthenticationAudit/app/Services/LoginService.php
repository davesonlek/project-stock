<?php

namespace Modules\AuthenticationAudit\Services;

use Illuminate\Support\Facades\Hash;
use Modules\AuthenticationAudit\Http\Resources\UserResource;
use Modules\AuthenticationAudit\Models\User;

class LoginService
{
    /**
     * Execute user authentication and issue JWT.
     */
    public function execute(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            AuditService::log(
                action: 'LOGIN_FAILED',
                entityType: 'Authentication',
                entityId: $user?->id,
                oldData: null,
                newData: ['email' => $email, 'reason' => 'INVALID_CREDENTIALS'],
                userId: $user?->id
            );

            return [
                'success' => false,
                'code' => 'INVALID_CREDENTIALS',
                'message' => 'Invalid email or password',
                'status_code' => 401,
            ];
        }

        if (!$user->is_active) {
            AuditService::log(
                action: 'LOGIN_FAILED',
                entityType: 'Authentication',
                entityId: $user->id,
                oldData: null,
                newData: ['email' => $email, 'reason' => 'ACCOUNT_INACTIVE'],
                userId: $user->id
            );

            return [
                'success' => false,
                'code' => 'ACCOUNT_INACTIVE',
                'message' => 'Your account has been deactivated',
                'status_code' => 403,
            ];
        }

        $token = auth('api')->login($user);

        if (!$token) {
            return [
                'success' => false,
                'code' => 'TOKEN_GENERATION_FAILED',
                'message' => 'Could not generate access token',
                'status_code' => 500,
            ];
        }

        $user->forceFill(['last_active_at' => now()])->save();

        AuditService::log(
            action: 'LOGIN_SUCCESS',
            entityType: 'Authentication',
            entityId: $user->id,
            oldData: null,
            newData: ['email' => $email],
            userId: $user->id
        );

        return [
            'success' => true,
            'data' => [
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => auth('api')->factory()->getTTL() * 60,
                'user' => new UserResource($user),
            ],
            'status_code' => 200,
        ];
    }
}
