<?php

namespace Modules\AuthenticationAudit\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\AuthenticationAudit\Models\UserOrganization;
use Modules\AuthenticationAudit\Services\OrganizationContext;
use Modules\AuthenticationAudit\Traits\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

class OrganizationContextMiddleware
{
    use ApiResponse;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $orgId = $request->header('X-Organization-Id');

        if (empty($orgId)) {
            return $this->errorResponse('ORGANIZATION_REQUIRED', 'X-Organization-Id header is required', [], 422);
        }

        if (!Str::isUuid($orgId)) {
            return $this->errorResponse('ORGANIZATION_NOT_FOUND', 'Organization not found', [], 404);
        }

        $organization = Organization::find($orgId);

        if (!$organization) {
            return $this->errorResponse('ORGANIZATION_NOT_FOUND', 'Organization not found', [], 404);
        }

        $user = auth('api')->user() ?? auth('web')->user() ?? $request->user();

        if (!$user) {
            return $this->errorResponse('UNAUTHENTICATED', 'Authentication required', [], 401);
        }

        $membership = UserOrganization::with('role')
            ->where('user_id', $user->id)
            ->where('organization_id', $organization->id)
            ->first();

        if (!$membership || !$membership->role) {
            return $this->errorResponse('ORGANIZATION_ACCESS_DENIED', 'You are not a member of this organization', [], 403);
        }

        $context = new OrganizationContext(
            user: $user,
            organization: $organization,
            membership: $membership,
            role: $membership->role
        );

        // Bind as request-scoped singleton
        app()->instance(OrganizationContext::class, $context);
        $request->attributes->set('organizationContext', $context);

        return $next($request);
    }
}
