<?php

namespace Modules\AuthenticationAudit\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\AuthenticationAudit\Models\AuditLog;
use Modules\AuthenticationAudit\Models\User;

class WebAuditLogController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', AuditLog::class);

        $orgId = session('current_organization_id');

        $query = AuditLog::where('organization_id', $orgId)->with('user');

        if ($action = $request->query('action')) {
            $query->where('action', 'ilike', "%{$action}%");
        }

        if ($entityType = $request->query('entity_type')) {
            $query->where('entity_type', $entityType);
        }

        if ($userId = $request->query('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($dateFrom = $request->query('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->query('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $perPage = min((int) $request->query('per_page', 20), 100);
        $logs = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $users = User::whereHas('userOrganizations', function ($q) use ($orgId) {
            $q->where('organization_id', $orgId);
        })->orderBy('username')->get();

        return view('history.audit-logs', compact('logs', 'users'));
    }

    public function show(int $id): View
    {
        Gate::authorize('viewAny', AuditLog::class);

        $orgId = session('current_organization_id');
        $log = AuditLog::where('organization_id', $orgId)->with('user')->findOrFail($id);

        return view('history.audit-log-detail', compact('log'));
    }
}
