@extends('layouts.app')

@section('page_title', 'Audit Trail Logs')

@section('content')
<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <div>
            <h6 class="mb-0 fw-bold"><i class="bi bi-shield-check me-2 text-primary"></i>System Audit Trail</h6>
            <small class="text-muted">Immutable log of security, authentication, and inventory actions</small>
        </div>
        <span class="badge bg-light text-dark border"><i class="bi bi-lock-fill me-1 text-secondary"></i>Immutable</span>
    </div>
    <div class="card-body border-bottom bg-light py-2">
        <form method="GET" action="{{ route('audit-logs.index') }}" class="row g-2 align-items-center">
            <div class="col-md-3">
                <input type="text" name="action" class="form-control form-control-sm" placeholder="Filter action (e.g. STOCK_POSTED)..." value="{{ request('action') }}">
            </div>
            <div class="col-md-2">
                <input type="text" name="entity_type" class="form-control form-control-sm" placeholder="Entity (e.g. StockDocument)..." value="{{ request('entity_type') }}">
            </div>
            <div class="col-md-2">
                <select name="user_id" class="form-select form-select-sm">
                    <option value="">All Users</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>{{ $u->username }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
            </div>
            <div class="col-md-2">
                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-sm btn-secondary w-100"><i class="bi bi-search"></i></button>
            </div>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Timestamp</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Entity Type</th>
                        <th>Entity ID</th>
                        <th>IP Address</th>
                        <th class="text-end">Details</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td class="small text-muted">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                            <td>
                                <div class="fw-semibold">{{ $log->user->username ?? 'System' }}</div>
                                <small class="text-muted">{{ $log->user->email ?? '' }}</small>
                            </td>
                            <td>
                                <span class="badge bg-secondary font-monospace">{{ $log->action }}</span>
                            </td>
                            <td>{{ $log->entity_type }}</td>
                            <td><code>{{ substr($log->entity_id, 0, 12) }}...</code></td>
                            <td class="small text-muted">{{ $log->ip_address }}</td>
                            <td class="text-end">
                                <a href="{{ route('audit-logs.show', $log->id) }}" class="btn btn-sm btn-outline-info py-0 px-2">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">No audit logs found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($logs->hasPages())
        <div class="card-footer bg-white py-2">
            {{ $logs->withQueryString()->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection
