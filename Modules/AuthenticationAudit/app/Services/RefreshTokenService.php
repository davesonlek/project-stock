<?php

namespace Modules\AuthenticationAudit\Services;

use Modules\AuthenticationAudit\Http\Resources\UserResource;
use Tymon\JWTAuth\Facades\JWTAuth;

class RefreshTokenService
{
    /**
     * Refresh JWT token.
     */
    public function execute(): array
    {
        $newToken = JWTAuth::refresh(JWTAuth::getToken());
        $user = auth('api')->user();

        return [
            'success' => true,
            'data' => [
                'access_token' => $newToken,
                'token_type' => 'bearer',
                'expires_in' => auth('api')->factory()->getTTL() * 60,
                'user' => $user ? new UserResource($user) : null,
            ],
            'status_code' => 200,
        ];
    }
}
