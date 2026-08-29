<?php

namespace Modules\AuthenticationAudit\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\AuthenticationAudit\Models\UserOrganization;

class WebAuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            $user = Auth::user();

            if (!$user->is_active) {
                Auth::logout();
                $request->session()->invalidate();
                return back()->withErrors(['email' => 'Your account is deactivated.']);
            }

            $userOrgs = UserOrganization::with('organization', 'role')
                ->where('user_id', $user->id)
                ->get();

            if ($userOrgs->isEmpty()) {
                Auth::logout();
                $request->session()->invalidate();
                return back()->withErrors(['email' => 'User does not belong to any active organization.']);
            }

            if ($userOrgs->count() === 1) {
                session(['current_organization_id' => $userOrgs->first()->organization_id]);
                return redirect()->intended(route('dashboard'));
            }

            return redirect()->route('organizations.select');
        }

        return back()->withInput($request->only('email', 'remember'))
            ->withErrors(['email' => 'Invalid email or password credentials.']);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('info', 'You have been logged out successfully.');
    }

    public function showSelectOrg(): View|RedirectResponse
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();
        $memberships = UserOrganization::with(['organization', 'role'])
            ->where('user_id', $user->id)
            ->get();

        if ($memberships->isEmpty()) {
            Auth::logout();
            return redirect()->route('login')->withErrors(['email' => 'User has no organization access.']);
        }

        return view('auth.select-org', compact('memberships'));
    }

    public function selectOrg(Request $request): RedirectResponse
    {
        $request->validate([
            'organization_id' => ['required', 'uuid'],
        ]);

        $user = Auth::user();
        $orgId = $request->input('organization_id');

        $membership = UserOrganization::where('user_id', $user->id)
            ->where('organization_id', $orgId)
            ->first();

        if (!$membership) {
            return back()->with('error', 'You are not a member of the selected organization.');
        }

        session(['current_organization_id' => $orgId]);

        return redirect()->route('dashboard')->with('success', 'Organization switched successfully.');
    }
}
