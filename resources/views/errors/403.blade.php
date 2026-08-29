@extends('layouts.auth')

@section('content')
<div class="card shadow-sm border-0 text-center">
    <div class="card-body p-5">
        <div class="display-1 fw-bold text-danger mb-2">403</div>
        <h4 class="fw-bold mb-3">Access Forbidden</h4>
        <p class="text-muted mb-4">You do not have permission to perform this action or access this resource.</p>
        <a href="{{ route('dashboard') }}" class="btn btn-primary px-4">
            <i class="bi bi-house-door me-2"></i>Return to Dashboard
        </a>
    </div>
</div>
@endsection
