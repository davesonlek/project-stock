<?php

namespace Modules\AuthenticationAudit\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\AuthenticationAudit\Traits\ApiResponse;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class JwtMiddleware
{
    use ApiResponse;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return $this->errorResponse('UNAUTHENTICATED', 'User not found', [], 401);
            }

            if (!$user->is_active) {
                return $this->errorResponse('ACCOUNT_INACTIVE', 'Your account has been deactivated', [], 403);
            }

            auth()->setUser($user);
            auth('api')->setUser($user);
            auth('web')->setUser($user);
            $request->setUserResolver(fn () => $user);

        } catch (TokenExpiredException $e) {
            return $this->errorResponse('TOKEN_EXPIRED', 'Token has expired', [], 401);
        } catch (TokenInvalidException $e) {
            return $this->errorResponse('TOKEN_INVALID', 'Token is invalid', [], 401);
        } catch (JWTException $e) {
            return $this->errorResponse('UNAUTHENTICATED', 'Authentication token required', [], 401);
        }

        return $next($request);
    }
}
