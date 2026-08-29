@extends('layouts.app')

@section('page_title', 'Warehouse Locations')

@section('content')
<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h6 class="mb-0 fw-bold"><i class="bi bi-geo-alt me-2 text-primary"></i>Warehouse Storage Locations</h6>
        @can('create', \Modules\MasterData\Models\WarehouseLocation::class)
            <a href="{{ route('warehouse-locations.create') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-circle me-1"></i>Add Location
            </a>
        @endcan
    </div>
    <div class="card-body border-bottom bg-light py-2">
        <form method="GET" action="{{ route('warehouse-locations.index') }}" class="row g-2 align-items-center">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search code, zone, rack..." value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <select name="warehouse_id" class="form-select form-select-sm">
                    <option value="">All Warehouses</option>
                    @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}" {{ request('warehouse_id') == $wh->id ? 'selected' : '' }}>{{ $wh->name }} ({{ $wh->code }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="is_active" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>Active</option>
                    <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-secondary w-100"><i class="bi bi-search me-1"></i>Filter</button>
            </div>
            <div class="col-md-2">
                <a href="{{ route('warehouse-locations.index') }}" class="btn btn-sm btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Warehouse</th>
                        <th>Location Code</th>
                        <th>Name</th>
                        <th>Hierarchy (Zone / Aisle / Rack / Shelf / Bin)</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($locations as $loc)
                        <tr>
                            <td>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                    {{ $loc->warehouse->name ?? 'WH' }}
                                </span>
                            </td>
                            <td class="fw-semibold text-primary">{{ $loc->code }}</td>
                            <td>{{ $loc->name }}</td>
                            <td>
                                <span class="small text-muted">
                                    Zone: <strong>{{ $loc->zone ?? '-' }}</strong> &bull;
                                    Aisle: <strong>{{ $loc->aisle ?? '-' }}</strong> &bull;
                                    Rack: <strong>{{ $loc->rack ?? '-' }}</strong> &bull;
                                    Shelf: <strong>{{ $loc->shelf ?? '-' }}</strong> &bull;
                                    Bin: <strong>{{ $loc->bin ?? '-' }}</strong>
                                </span>
                            </td>
                            <td>
                                @if($loc->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-secondary">Inactive</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @can('update', $loc)
                                    <a href="{{ route('warehouse-locations.edit', $loc->id) }}" class="btn btn-sm btn-outline-primary py-0 px-2 me-1" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    @if($loc->is_active)
                                        <form method="POST" action="{{ route('warehouse-locations.deactivate', $loc->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2" title="Deactivate">
                                                <i class="bi bi-slash-circle"></i>
                                            </button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('warehouse-locations.activate', $loc->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-success py-0 px-2" title="Activate">
                                                <i class="bi bi-check-circle"></i>
                                            </button>
                                        </form>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No locations found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($locations->hasPages())
        <div class="card-footer bg-white py-2">
            {{ $locations->withQueryString()->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection
