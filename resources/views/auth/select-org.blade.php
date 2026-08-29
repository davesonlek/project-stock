@extends('layouts.auth')

@section('content')
<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <h4 class="card-title fw-bold text-center mb-2">Select Organization</h4>
        <p class="text-muted text-center small mb-4">Choose an organization to continue</p>

        <form method="POST" action="{{ route('organizations.select.submit') }}">
            @csrf

            <div class="list-group mb-4">
                @foreach($memberships as $membership)
                    <label class="list-group-item list-group-item-action d-flex justify-content-between align-items-center p-3 cursor-pointer">
                        <div class="d-flex align-items-center">
                            <input class="form-check-input me-3" type="radio" name="organization_id" value="{{ $membership->organization_id }}" {{ $loop->first ? 'checked' : '' }}>
                            <div>
                                <h6 class="mb-0 fw-bold">{{ $membership->organization->name }}</h6>
                                <small class="text-muted">Code: {{ $membership->organization->code }}</small>
                            </div>
                        </div>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1">
                            {{ $membership->role->name ?? $membership->role->code }}
                        </span>
                    </label>
                @endforeach
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                <i class="bi bi-arrow-right-circle me-2"></i>Enter Organization
            </button>
        </form>
    </div>
</div>
@endsection
