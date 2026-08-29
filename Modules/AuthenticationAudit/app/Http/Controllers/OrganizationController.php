<?php

namespace Modules\AuthenticationAudit\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AuthenticationAudit\Http\Resources\CurrentOrganizationResource;
use Modules\AuthenticationAudit\Http\Resources\OrganizationResource;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\AuthenticationAudit\Services\OrganizationContext;
use Modules\AuthenticationAudit\Traits\ApiResponse;

class OrganizationController extends Controller
{
    use ApiResponse;

    /**
     * List all organizations the authenticated user belongs to.
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth('api')->user() ?? $request->user();

        if (!$user) {
            return $this->errorResponse('UNAUTHENTICATED', 'User not authenticated', [], 401);
        }

        $organizations = $user->organizations()->get();

        return $this->successResponse(OrganizationResource::collection($organizations));
    }

    /**
     * Get current active organization and role from OrganizationContext.
     */
    public function current(Request $request): JsonResponse
    {
        $context = app()->bound(OrganizationContext::class) ? app(OrganizationContext::class) : null;

        if (!$context) {
            return $this->errorResponse('ORGANIZATION_REQUIRED', 'No active organization context found', [], 422);
        }

        return $this->successResponse(new CurrentOrganizationResource($context));
    }
}
