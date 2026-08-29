@extends('layouts.app')

@section('page_title', 'Suppliers')

@section('content')
<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h6 class="mb-0 fw-bold"><i class="bi bi-truck me-2 text-primary"></i>Suppliers</h6>
        @can('create', \Modules\MasterData\Models\Supplier::class)
            <a href="{{ route('suppliers.create') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-circle me-1"></i>Add Supplier
            </a>
        @endcan
    </div>
    <div class="card-body border-bottom bg-light py-2">
        <form method="GET" action="{{ route('suppliers.index') }}" class="row g-2 align-items-center">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by code, name, contact..." value="{{ request('search') }}">
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
                <a href="{{ route('suppliers.index') }}" class="btn btn-sm btn-outline-secondary w-100">Reset</a>
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
                        <th>Contact / Phone</th>
                        <th>Email</th>
                        <th>Tax ID</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($suppliers as $supplier)
                        <tr>
                            <td class="fw-semibold text-primary">{{ $supplier->code }}</td>
                            <td>{{ $supplier->name }}</td>
                            <td>
                                <div>{{ $supplier->contact_name ?? '-' }}</div>
                                <small class="text-muted">{{ $supplier->phone ?? '' }}</small>
                            </td>
                            <td>{{ $supplier->email ?? '-' }}</td>
                            <td>{{ $supplier->tax_id ?? '-' }}</td>
                            <td>
                                @if($supplier->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-secondary">Inactive</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @can('update', $supplier)
                                    <a href="{{ route('suppliers.edit', $supplier->id) }}" class="btn btn-sm btn-outline-primary py-0 px-2 me-1" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    @if($supplier->is_active)
                                        <form method="POST" action="{{ route('suppliers.deactivate', $supplier->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2" title="Deactivate">
                                                <i class="bi bi-slash-circle"></i>
                                            </button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('suppliers.activate', $supplier->id) }}" class="d-inline">
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
                            <td colspan="7" class="text-center py-4 text-muted">No suppliers found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($suppliers->hasPages())
        <div class="card-footer bg-white py-2">
            {{ $suppliers->withQueryString()->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection
