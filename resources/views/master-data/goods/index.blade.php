@extends('layouts.app')

@section('page_title', 'Goods (SKUs)')

@section('content')
<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h6 class="mb-0 fw-bold"><i class="bi bi-grid me-2 text-primary"></i>Goods (Stock Keeping Units)</h6>
        @can('create', \Modules\MasterData\Models\Goods::class)
            <a href="{{ route('goods.create') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-circle me-1"></i>Add Goods SKU
            </a>
        @endcan
    </div>
    <div class="card-body border-bottom bg-light py-2">
        <form method="GET" action="{{ route('goods.index') }}" class="row g-2 align-items-center">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search SKU, name, barcode..." value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <select name="product_id" class="form-select form-select-sm">
                    <option value="">All Products</option>
                    @foreach($products as $p)
                        <option value="{{ $p->id }}" {{ request('product_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="unit_id" class="form-select form-select-sm">
                    <option value="">All Units</option>
                    @foreach($units as $u)
                        <option value="{{ $u->id }}" {{ request('unit_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
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
            <div class="col-md-1">
                <button type="submit" class="btn btn-sm btn-secondary w-100"><i class="bi bi-search"></i></button>
            </div>
            <div class="col-md-2">
                <a href="{{ route('goods.index') }}" class="btn btn-sm btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>SKU</th>
                        <th>Product</th>
                        <th>Unit & Pack</th>
                        <th>Tracking Type</th>
                        <th>Cost / Sell Price</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($goodsList as $goods)
                        <tr>
                            <td class="fw-semibold text-primary">
                                <div>{{ $goods->sku }}</div>
                                @if($goods->barcode)
                                    <small class="text-muted"><i class="bi bi-upc me-1"></i>{{ $goods->barcode }}</small>
                                @endif
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $goods->product->name ?? '-' }}</div>
                                <small class="text-muted">{{ $goods->product->sku ?? '' }}</small>
                            </td>
                            <td>
                                <div>{{ $goods->unit->name ?? 'Unit' }}</div>
                                <small class="text-muted">Pack size: {{ $goods->pack_size }}</small>
                            </td>
                            <td>
                                @if($goods->is_lot_tracked && $goods->is_serial_tracked)
                                    <span class="badge bg-purple text-white" style="background-color: #7c3aed;">LOT + SERIAL</span>
                                @elseif($goods->is_lot_tracked)
                                    <span class="badge bg-warning text-dark">LOT</span>
                                @elseif($goods->is_serial_tracked)
                                    <span class="badge bg-info text-dark">SERIAL</span>
                                @else
                                    <span class="badge bg-light text-dark border">STANDARD</span>
                                @endif
                            </td>
                            <td>
                                <div>${{ number_format((float)($goods->cost_price ?? 0), 2) }}</div>
                                <small class="text-muted">${{ number_format((float)($goods->selling_price ?? 0), 2) }}</small>
                            </td>
                            <td>
                                @if($goods->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-secondary">Inactive</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @can('update', $goods)
                                    <a href="{{ route('goods.edit', $goods->id) }}" class="btn btn-sm btn-outline-primary py-0 px-2 me-1" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    @if($goods->is_active)
                                        <form method="POST" action="{{ route('goods.deactivate', $goods->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2" title="Deactivate">
                                                <i class="bi bi-slash-circle"></i>
                                            </button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('goods.activate', $goods->id) }}" class="d-inline">
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
                            <td colspan="7" class="text-center py-4 text-muted">No goods (SKUs) found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($goodsList->hasPages())
        <div class="card-footer bg-white py-2">
            {{ $goodsList->withQueryString()->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection
