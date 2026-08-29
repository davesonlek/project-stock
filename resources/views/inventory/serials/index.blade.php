@extends('layouts.app')

@section('page_title', 'Serial Numbers')

@section('content')
<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h6 class="mb-0 fw-bold"><i class="bi bi-upc-scan me-2 text-primary"></i>Piece-Level Serial Numbers</h6>
    </div>
    <div class="card-body border-bottom bg-light py-2">
        <form method="GET" action="{{ route('inventory.serials') }}" class="row g-2 align-items-center">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search serial no..." value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <select name="goods_id" class="form-select form-select-sm">
                    <option value="">All Serial Tracked Goods</option>
                    @foreach($goodsList as $g)
                        <option value="{{ $g->id }}" {{ request('goods_id') == $g->id ? 'selected' : '' }}>{{ $g->name }} ({{ $g->sku }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="IN_STOCK" {{ request('status') === 'IN_STOCK' ? 'selected' : '' }}>IN_STOCK</option>
                    <option value="RESERVED" {{ request('status') === 'RESERVED' ? 'selected' : '' }}>RESERVED</option>
                    <option value="ISSUED" {{ request('status') === 'ISSUED' ? 'selected' : '' }}>ISSUED</option>
                    <option value="REVERSED" {{ request('status') === 'REVERSED' ? 'selected' : '' }}>REVERSED</option>
                    <option value="DAMAGED" {{ request('status') === 'DAMAGED' ? 'selected' : '' }}>DAMAGED</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-secondary w-100"><i class="bi bi-search me-1"></i>Filter</button>
            </div>
            <div class="col-md-2">
                <a href="{{ route('inventory.serials') }}" class="btn btn-sm btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Serial Number</th>
                        <th>Goods / SKU</th>
                        <th>Lot No</th>
                        <th>Warehouse</th>
                        <th>Location</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($serials as $serial)
                        <tr>
                            <td>
                                <code class="fw-bold fs-6">{{ $serial->serial_no }}</code>
                            </td>
                            <td>
                                <div class="fw-semibold text-primary">{{ $serial->goods->product->name ?? 'Product' }}</div>
                                <small class="text-muted">{{ $serial->goods->sku ?? '-' }}</small>
                            </td>
                            <td>
                                @if($serial->stockLot)
                                    <span class="badge bg-light text-dark border">{{ $serial->stockLot->lot_no }}</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>{{ $serial->warehouse->name ?? '-' }}</td>
                            <td><code>{{ $serial->location->code ?? '-' }}</code></td>
                            <td>
                                @if($serial->status->value === 'IN_STOCK')
                                    <span class="badge bg-success">IN_STOCK</span>
                                @elseif($serial->status->value === 'RESERVED')
                                    <span class="badge bg-warning text-dark">RESERVED</span>
                                @elseif($serial->status->value === 'ISSUED')
                                    <span class="badge bg-info text-dark">ISSUED</span>
                                @elseif($serial->status->value === 'REVERSED')
                                    <span class="badge bg-danger">REVERSED</span>
                                @else
                                    <span class="badge bg-secondary">{{ $serial->status->value }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No serial numbers found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($serials->hasPages())
        <div class="card-footer bg-white py-2">
            {{ $serials->withQueryString()->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection
