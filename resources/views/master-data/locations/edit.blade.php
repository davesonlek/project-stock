@extends('layouts.app')

@section('page_title', 'Edit Location')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-pencil me-2 text-primary"></i>Edit Location: {{ $location->code }}</h6>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-light border mb-3 small">
                    <strong>Warehouse:</strong> {{ $location->warehouse->name ?? '-' }} ({{ $location->warehouse->code ?? '-' }})
                </div>

                <form method="POST" action="{{ route('warehouse-locations.update', $location->id) }}">
                    @csrf
                    @method('PUT')

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Location Code <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code', $location->code) }}" required>
                            @error('code')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Location Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $location->name) }}" required>
                            @error('name')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Hierarchy Coordinates -->
                    <div class="card bg-light border p-3 mb-4">
                        <h6 class="fw-bold mb-2">Location Coordinates</h6>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label small text-muted">Zone</label>
                                <input type="text" name="zone" class="form-control form-control-sm" value="{{ old('zone', $location->zone) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-muted">Aisle</label>
                                <input type="text" name="aisle" class="form-control form-control-sm" value="{{ old('aisle', $location->aisle) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-muted">Rack</label>
                                <input type="text" name="rack" class="form-control form-control-sm" value="{{ old('rack', $location->rack) }}">
                            </div>
                            <div class="col-md-6 mt-2">
                                <label class="form-label small text-muted">Shelf</label>
                                <input type="text" name="shelf" class="form-control form-control-sm" value="{{ old('shelf', $location->shelf) }}">
                            </div>
                            <div class="col-md-6 mt-2">
                                <label class="form-label small text-muted">Bin</label>
                                <input type="text" name="bin" class="form-control form-control-sm" value="{{ old('bin', $location->bin) }}">
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('warehouse-locations.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">Update Location</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
