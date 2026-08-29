@extends('layouts.app')

@section('page_title', 'Audit Log Detail #' . $log->id)

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-shield-check me-2 text-primary"></i>Audit Log Record #{{ $log->id }}</h6>
                <a href="{{ route('audit-logs.index') }}" class="btn btn-sm btn-outline-secondary">Back to Logs</a>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <span class="text-muted small d-block">Action:</span>
                        <span class="badge bg-secondary font-monospace">{{ $log->action }}</span>
                    </div>
                    <div class="col-md-3">
                        <span class="text-muted small d-block">Entity Type:</span>
                        <strong>{{ $log->entity_type }}</strong>
                    </div>
                    <div class="col-md-3">
                        <span class="text-muted small d-block">Entity ID:</span>
                        <code>{{ $log->entity_id }}</code>
                    </div>
                    <div class="col-md-3">
                        <span class="text-muted small d-block">Timestamp:</span>
                        <span>{{ $log->created_at->format('Y-m-d H:i:s') }}</span>
                    </div>
                </div>

                <div class="row g-3 mb-4 small border-top pt-3">
                    <div class="col-md-4">
                        <span class="text-muted d-block">User:</span>
                        <strong>{{ $log->user->username ?? 'System' }}</strong> ({{ $log->user->email ?? '-' }})
                    </div>
                    <div class="col-md-4">
                        <span class="text-muted d-block">IP Address:</span>
                        <code>{{ $log->ip_address }}</code>
                    </div>
                    <div class="col-md-4">
                        <span class="text-muted d-block">User Agent / Device:</span>
                        <span class="text-truncate d-block">{{ $log->user_agent ?? '-' }}</span>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <h6 class="fw-bold text-muted small text-uppercase">Old Data (Pre-Mutation State)</h6>
                        <pre class="bg-light p-3 border rounded small font-monospace" style="max-height: 350px; overflow: auto;">{{ json_encode($log->old_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: 'null' }}</pre>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-bold text-muted small text-uppercase">New Data (Post-Mutation State)</h6>
                        <pre class="bg-light p-3 border rounded small font-monospace" style="max-height: 350px; overflow: auto;">{{ json_encode($log->new_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: 'null' }}</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
