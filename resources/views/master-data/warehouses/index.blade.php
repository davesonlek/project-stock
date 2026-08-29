@extends('layouts.app')

@section('page_title', 'Warehouses')

@section('content')
<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h6 class="mb-0 fw-bold"><i class="bi bi-building me-2 text-primary"></i>Warehouses</h6>
        @can('create', \Modules\MasterData\Models\Warehouse::class)
            <a href="{{ route('warehouses.create') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-circle me-1"></i>Add Warehouse
            </a>
        @endcan
    </div>
    <div class="card-body border-bottom bg-light py-2">
        <form method="GET" action="{{ route('warehouses.index') }}" class="row g-2 align-items-center">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by code or name..." value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
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
                <a href="{{ route('warehouses.index') }}" class="btn btn-sm btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Manager</th>
                        <th>Locations</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($warehouses as $warehouse)
                        <tr>
                            <td class="fw-semibold text-primary">
                                <a href="{{ route('warehouses.show', $warehouse->id) }}" class="text-decoration-none">{{ $warehouse->code }}</a>
                            </td>
                            <td>{{ $warehouse->name }}</td>
                            <td>
                                @if($warehouse->manager)
                                    <div>{{ $warehouse->manager->username }}</div>
                                    <small class="text-muted">{{ $warehouse->manager->email }}</small>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">{{ $warehouse->locations_count ?? $warehouse->locations()->count() }} locations</span>
                            </td>
                            <td>
                                @if($warehouse->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-secondary">Inactive</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('warehouses.show', $warehouse->id) }}" class="btn btn-sm btn-outline-info py-0 px-2 me-1" title="View Details">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @can('update', $warehouse)
                                    <a href="{{ route('warehouses.edit', $warehouse->id) }}" class="btn btn-sm btn-outline-primary py-0 px-2 me-1" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    @if($warehouse->is_active)
                                        <form method="POST" action="{{ route('warehouses.deactivate', $warehouse->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2" title="Deactivate">
                                                <i class="bi bi-slash-circle"></i>
                                            </button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('warehouses.activate', $warehouse->id) }}" class="d-inline">
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
                            <td colspan="6" class="text-center py-4 text-muted">No warehouses found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($warehouses->hasPages())
        <div class="card-footer bg-white py-2">
            {{ $warehouses->withQueryString()->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection
