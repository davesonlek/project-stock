@extends('layouts.app')

@section('page_title', 'Stock Balances')

@section('content')
<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h6 class="mb-0 fw-bold"><i class="bi bi-box-seam me-2 text-primary"></i>Warehouse Stock Balances</h6>
    </div>
    <div class="card-body border-bottom bg-light py-2">
        <form method="GET" action="{{ route('inventory.stock') }}" class="row g-2 align-items-center">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search SKU, product..." value="{{ request('search') }}">
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
                <select name="stock_filter" class="form-select form-select-sm">
                    <option value="">All Stock Levels</option>
                    <option value="has_stock" {{ request('stock_filter') === 'has_stock' ? 'selected' : '' }}>Available Stock &gt; 0</option>
                    <option value="reserved_only" {{ request('stock_filter') === 'reserved_only' ? 'selected' : '' }}>Reserved &gt; 0</option>
                    <option value="zero_stock" {{ request('stock_filter') === 'zero_stock' ? 'selected' : '' }}>Zero Stock</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-secondary w-100"><i class="bi bi-search me-1"></i>Filter</button>
            </div>
            <div class="col-md-2">
                <a href="{{ route('inventory.stock') }}" class="btn btn-sm btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Product & Goods (SKU)</th>
                        <th>Warehouse</th>
                        <th>Location</th>
                        <th class="text-end">On Hand</th>
                        <th class="text-end">Reserved</th>
                        <th class="text-end">Available</th>
                        <th class="text-center">Stock Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($balances as $balance)
                        @php
                            $onHand = (float)$balance->on_hand;
                            $reserved = (float)$balance->reserved;
                            $available = $onHand - $reserved;
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-semibold text-primary">{{ $balance->goods->product->name ?? 'Product' }}</div>
                                <small class="text-muted">SKU: {{ $balance->goods->sku ?? '-' }} ({{ $balance->goods->unit->name ?? 'Unit' }})</small>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">{{ $balance->warehouse->name ?? 'WH' }}</span>
                            </td>
                            <td>
                                <code>{{ $balance->location->code ?? '-' }}</code>
                            </td>
                            <td class="text-end fw-bold text-dark">{{ number_format($onHand, 2) }}</td>
                            <td class="text-end fw-semibold text-warning">
                                @if($reserved > 0)
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle">{{ number_format($reserved, 2) }}</span>
                                @else
                                    <span class="text-muted">0.00</span>
                                @endif
                            </td>
                            <td class="text-end fw-bold text-primary">{{ number_format($available, 2) }}</td>
                            <td class="text-center">
                                @if($available > 0)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">Normal</span>
                                @elseif($onHand > 0 && $available <= 0)
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Fully Reserved</span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Out of Stock</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">No stock balances found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($balances->hasPages())
        <div class="card-footer bg-white py-2">
            {{ $balances->withQueryString()->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection
