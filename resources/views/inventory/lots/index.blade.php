@extends('layouts.app')

@section('page_title', 'Stock Lots')

@section('content')
<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h6 class="mb-0 fw-bold"><i class="bi bi-tags me-2 text-primary"></i>Stock Lots & Expiration Tracking</h6>
    </div>
    <div class="card-body border-bottom bg-light py-2">
        <form method="GET" action="{{ route('inventory.lots') }}" class="row g-2 align-items-center">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search lot no, SKU..." value="{{ request('search') }}">
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
                <select name="status_filter" class="form-select form-select-sm">
                    <option value="">All Expiration Status</option>
                    <option value="ACTIVE" {{ request('status_filter') === 'ACTIVE' ? 'selected' : '' }}>Active (Valid)</option>
                    <option value="NEAR_EXPIRY" {{ request('status_filter') === 'NEAR_EXPIRY' ? 'selected' : '' }}>Near Expiry (&le; 30d)</option>
                    <option value="EXPIRED" {{ request('status_filter') === 'EXPIRED' ? 'selected' : '' }}>Expired</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-secondary w-100"><i class="bi bi-search me-1"></i>Filter</button>
            </div>
            <div class="col-md-2">
                <a href="{{ route('inventory.lots') }}" class="btn btn-sm btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Lot Number</th>
                        <th>Goods (SKU)</th>
                        <th>Warehouse / Loc</th>
                        <th>Mfg Date</th>
                        <th>Expiry Date</th>
                        <th class="text-end">On Hand</th>
                        <th class="text-end">Reserved</th>
                        <th class="text-end">Available</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($lotBalances as $lotBal)
                        @php
                            $onHand = (float)$lotBal->on_hand;
                            $reserved = (float)$lotBal->reserved;
                            $available = $onHand - $reserved;
                            $lot = $lotBal->stockLot;
                            $today = \Carbon\Carbon::today();
                            $expiredAt = $lot && $lot->expired_at ? \Carbon\Carbon::parse($lot->expired_at) : null;
                        @endphp
                        <tr>
                            <td>
                                <span class="badge bg-dark font-monospace fs-6">{{ $lot->lot_no ?? '-' }}</span>
                            </td>
                            <td>
                                <div class="fw-semibold text-primary">{{ $lot->goods->product->name ?? 'Product' }}</div>
                                <small class="text-muted">{{ $lot->goods->sku ?? '' }} ({{ $lot->goods->unit->name ?? 'Unit' }})</small>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">{{ $lotBal->warehouse->code ?? 'WH' }}</span>
                                <span class="small text-muted">&bull; {{ $lotBal->location->code ?? '-' }}</span>
                            </td>
                            <td class="small text-muted">{{ $lot && $lot->manufactured_at ? \Carbon\Carbon::parse($lot->manufactured_at)->format('Y-m-d') : '-' }}</td>
                            <td>
                                @if($expiredAt)
                                    <span class="small fw-semibold {{ $expiredAt->isPast() ? 'text-danger' : ($expiredAt->diffInDays($today) <= 30 ? 'text-warning' : 'text-dark') }}">
                                        {{ $expiredAt->format('Y-m-d') }}
                                    </span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="text-end fw-bold text-dark">{{ number_format($onHand, 2) }}</td>
                            <td class="text-end fw-semibold text-warning">{{ number_format($reserved, 2) }}</td>
                            <td class="text-end fw-bold text-primary">{{ number_format($available, 2) }}</td>
                            <td class="text-center">
                                @if($expiredAt && $expiredAt->isPast())
                                    <span class="badge bg-danger">EXPIRED</span>
                                @elseif($expiredAt && $expiredAt->diffInDays($today) <= 30)
                                    <span class="badge bg-warning text-dark">NEAR EXPIRY</span>
                                @else
                                    <span class="badge bg-success">ACTIVE</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">No stock lot balances found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($lotBalances->hasPages())
        <div class="card-footer bg-white py-2">
            {{ $lotBalances->withQueryString()->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection
