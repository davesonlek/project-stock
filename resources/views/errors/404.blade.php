@extends('layouts.auth')

@section('content')
<div class="card shadow-sm border-0 text-center">
    <div class="card-body p-5">
        <div class="display-1 fw-bold text-secondary mb-2">404</div>
        <h4 class="fw-bold mb-3">Resource Not Found</h4>
        <p class="text-muted mb-4">The requested page or record could not be found.</p>
        <a href="{{ route('dashboard') }}" class="btn btn-primary px-4">
            <i class="bi bi-house-door me-2"></i>Return to Dashboard
        </a>
    </div>
</div>
@endsection
