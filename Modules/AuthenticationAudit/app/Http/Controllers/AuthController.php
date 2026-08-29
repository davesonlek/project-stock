<?php

namespace Modules\AuthenticationAudit\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AuthenticationAudit\Http\Requests\LoginRequest;
use Modules\AuthenticationAudit\Http\Resources\UserResource;
use Modules\AuthenticationAudit\Services\LoginService;
use Modules\AuthenticationAudit\Services\LogoutService;
use Modules\AuthenticationAudit\Services\RefreshTokenService;
use Modules\AuthenticationAudit\Traits\ApiResponse;

class AuthController extends Controller
{
    use ApiResponse;

    public function login(LoginRequest $request, LoginService $loginService): JsonResponse
    {
        $result = $loginService->execute(
            $request->input('email'),
            $request->input('password')
        );

        if (!$result['success']) {
            return $this->errorResponse(
                $result['code'],
                $result['message'],
                [],
                $result['status_code']
            );
        }

        return $this->successResponse($result['data'], 'Login successful', $result['status_code']);
    }

    public function refresh(RefreshTokenService $refreshService): JsonResponse
    {
        $result = $refreshService->execute();

        return $this->successResponse($result['data'], 'Token refreshed', $result['status_code']);
    }

    public function logout(LogoutService $logoutService): JsonResponse
    {
        $result = $logoutService->execute();

        return $this->successResponse([], $result['message'], $result['status_code']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = auth('api')->user() ?? $request->user();

        if (!$user) {
            return $this->errorResponse('UNAUTHENTICATED', 'User not authenticated', [], 401);
        }

        return $this->successResponse(new UserResource($user));
    }
}
