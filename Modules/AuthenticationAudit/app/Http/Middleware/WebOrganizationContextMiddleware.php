<?php

namespace Modules\AuthenticationAudit\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\AuthenticationAudit\Models\UserOrganization;
use Modules\AuthenticationAudit\Services\OrganizationContext;
use Symfony\Component\HttpFoundation\Response;

class WebOrganizationContextMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();
        $orgId = session('current_organization_id');

        // If no organization selected in session
        if (empty($orgId)) {
            // Check how many organizations user belongs to
            $userOrgs = UserOrganization::with('organization', 'role')
                ->where('user_id', $user->id)
                ->get();

            if ($userOrgs->isEmpty()) {
                auth()->logout();
                session()->invalidate();
                return redirect()->route('login')->withErrors(['email' => 'User does not belong to any organization.']);
            }

            if ($userOrgs->count() === 1) {
                $singleOrg = $userOrgs->first();
                session(['current_organization_id' => $singleOrg->organization_id]);
                $orgId = $singleOrg->organization_id;
            } else {
                return redirect()->route('organizations.select');
            }
        }

        // Validate membership on EVERY request
        $membership = UserOrganization::with(['organization', 'role'])
            ->where('user_id', $user->id)
            ->where('organization_id', $orgId)
            ->first();

        if (!$membership || !$membership->organization || !$membership->role) {
            session()->forget('current_organization_id');
            return redirect()->route('organizations.select')->with('error', 'Invalid organization membership. Please select an organization.');
        }

        $organization = $membership->organization;

        $context = new OrganizationContext(
            user: $user,
            organization: $organization,
            membership: $membership,
            role: $membership->role
        );

        // Bind as request-scoped singleton
        app()->instance(OrganizationContext::class, $context);
        $request->attributes->set('organizationContext', $context);

        // Share globally with all Blade views
        $allUserMemberships = UserOrganization::with('organization', 'role')
            ->where('user_id', $user->id)
            ->get();

        view()->share('currentOrgContext', $context);
        view()->share('currentOrganization', $organization);
        view()->share('currentRole', $membership->role);
        view()->share('currentUser', $user);
        view()->share('userOrganizations', $allUserMemberships);

        return $next($request);
    }
}
