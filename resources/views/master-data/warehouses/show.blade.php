@extends('layouts.app')

@section('page_title', 'Warehouse Details')

@section('content')
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-building me-2 text-primary"></i>Warehouse Info</h6>
                @can('update', $warehouse)
                    <a href="{{ route('warehouses.edit', $warehouse->id) }}" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-pencil me-1"></i>Edit
                    </a>
                @endcan
            </div>
            <div class="card-body">
                <h5 class="fw-bold mb-1">{{ $warehouse->name }}</h5>
                <p class="text-muted small mb-3">Code: <strong class="text-dark">{{ $warehouse->code }}</strong></p>

                <table class="table table-sm table-borderless small mb-0">
                    <tr>
                        <td class="text-muted" style="width: 40%;">Manager:</td>
                        <td class="fw-semibold">{{ $warehouse->manager->username ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Total Locations:</td>
                        <td>{{ $warehouse->locations->count() }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Status:</td>
                        <td>
                            @if($warehouse->is_active)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-secondary">Inactive</span>
                            @endif
                        </td>
                    </tr>
                </table>

                @if($warehouse->address)
                    <hr class="my-3">
                    <h6 class="small fw-bold text-muted text-uppercase mb-1">Address</h6>
                    <p class="small text-secondary mb-0">{{ $warehouse->address }}</p>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-geo-alt me-2 text-primary"></i>Storage Locations</h6>
                @can('create', \Modules\MasterData\Models\WarehouseLocation::class)
                    <a href="{{ route('warehouse-locations.create', ['warehouse_id' => $warehouse->id]) }}" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus-circle me-1"></i>Add Location
                    </a>
                @endcan
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Zone / Aisle</th>
                                <th>Rack / Shelf / Bin</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($warehouse->locations as $loc)
                                <tr>
                                    <td class="fw-semibold text-primary">{{ $loc->code }}</td>
                                    <td>{{ $loc->name }}</td>
                                    <td>
                                        <span>{{ $loc->zone ?? '-' }}</span> / <small class="text-muted">{{ $loc->aisle ?? '-' }}</small>
                                    </td>
                                    <td>
                                        <small class="text-muted">R: {{ $loc->rack ?? '-' }} | S: {{ $loc->shelf ?? '-' }} | B: {{ $loc->bin ?? '-' }}</small>
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
                                            <a href="{{ route('warehouse-locations.edit', $loc->id) }}" class="btn btn-sm btn-outline-primary py-0 px-2">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No locations defined in this warehouse yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
