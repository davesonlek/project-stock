@extends('layouts.app')

@section('page_title', 'Create Location')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-plus-circle me-2 text-primary"></i>Create New Storage Location</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('warehouse-locations.store') }}">
                    @csrf

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Warehouse <span class="text-danger">*</span></label>
                            <select name="warehouse_id" class="form-select @error('warehouse_id') is-invalid @enderror" required>
                                <option value="">-- Select Warehouse --</option>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}" {{ old('warehouse_id', $selectedWarehouseId) == $wh->id ? 'selected' : '' }}>
                                        {{ $wh->name }} ({{ $wh->code }})
                                    </option>
                                @endforeach
                            </select>
                            @error('warehouse_id')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Location Code <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code') }}" required placeholder="e.g. A-01-01">
                            @error('code')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Location Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required placeholder="e.g. Zone A / Aisle 1 / Shelf 1">
                        @error('name')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Hierarchy Coordinates -->
                    <div class="card bg-light border p-3 mb-4">
                        <h6 class="fw-bold mb-2">Location Coordinates</h6>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label small text-muted">Zone</label>
                                <input type="text" name="zone" class="form-control form-control-sm" value="{{ old('zone') }}" placeholder="Zone A">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-muted">Aisle</label>
                                <input type="text" name="aisle" class="form-control form-control-sm" value="{{ old('aisle') }}" placeholder="A-01">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-muted">Rack</label>
                                <input type="text" name="rack" class="form-control form-control-sm" value="{{ old('rack') }}" placeholder="Rack 1">
                            </div>
                            <div class="col-md-6 mt-2">
                                <label class="form-label small text-muted">Shelf</label>
                                <input type="text" name="shelf" class="form-control form-control-sm" value="{{ old('shelf') }}" placeholder="Shelf 1">
                            </div>
                            <div class="col-md-6 mt-2">
                                <label class="form-label small text-muted">Bin</label>
                                <input type="text" name="bin" class="form-control form-control-sm" value="{{ old('bin') }}" placeholder="Bin 1">
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('warehouse-locations.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">Create Location</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
