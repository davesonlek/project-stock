@extends('layouts.app')

@section('page_title', 'Stock Reservations')

@section('content')
<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h6 class="mb-0 fw-bold"><i class="bi bi-bookmark-check me-2 text-primary"></i>Stock Reservations</h6>
    </div>
    <div class="card-body border-bottom bg-light py-2">
        <form method="GET" action="{{ route('inventory.reservations') }}" class="row g-2 align-items-center">
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="ACTIVE" {{ request('status') === 'ACTIVE' ? 'selected' : '' }}>ACTIVE</option>
                    <option value="CONSUMED" {{ request('status') === 'CONSUMED' ? 'selected' : '' }}>CONSUMED</option>
                    <option value="RELEASED" {{ request('status') === 'RELEASED' ? 'selected' : '' }}>RELEASED</option>
                    <option value="EXPIRED" {{ request('status') === 'EXPIRED' ? 'selected' : '' }}>EXPIRED</option>
                    <option value="CANCELLED" {{ request('status') === 'CANCELLED' ? 'selected' : '' }}>CANCELLED</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="warehouse_id" class="form-select form-select-sm">
                    <option value="">All Warehouses</option>
                    @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}" {{ request('warehouse_id') == $wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-secondary w-100"><i class="bi bi-search me-1"></i>Filter</button>
            </div>
            <div class="col-md-2">
                <a href="{{ route('inventory.reservations') }}" class="btn btn-sm btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Reservation ID</th>
                        <th>Document</th>
                        <th>Goods / SKU</th>
                        <th>Warehouse / Location</th>
                        <th class="text-end">Quantity</th>
                        <th>Status</th>
                        <th>Expires At</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reservations as $res)
                        <tr>
                            <td>
                                <code class="small">{{ substr($res->id, 0, 8) }}...</code>
                            </td>
                            <td>
                                <a href="{{ route('stock.documents.show', $res->document_id) }}" class="fw-semibold text-decoration-none">
                                    #{{ substr($res->document_id, 0, 8) }}
                                </a>
                            </td>
                            <td>
                                <div class="fw-semibold text-primary">{{ $res->goods->name ?? 'Goods' }}</div>
                                <small class="text-muted">{{ $res->goods->sku ?? '' }}</small>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">{{ $res->warehouse->name ?? '-' }}</span>
                                <span class="small text-muted">&bull; {{ $res->location->code ?? '-' }}</span>
                            </td>
                            <td class="text-end fw-bold text-warning">{{ number_format((float)$res->quantity, 2) }}</td>
                            <td>
                                @if($res->status->value === 'ACTIVE')
                                    <span class="badge bg-warning text-dark">ACTIVE</span>
                                @elseif($res->status->value === 'CONSUMED')
                                    <span class="badge bg-success">CONSUMED</span>
                                @elseif($res->status->value === 'RELEASED')
                                    <span class="badge bg-info text-dark">RELEASED</span>
                                @elseif($res->status->value === 'EXPIRED')
                                    <span class="badge bg-danger">EXPIRED</span>
                                @else
                                    <span class="badge bg-secondary">{{ $res->status->value }}</span>
                                @endif
                            </td>
                            <td class="small text-muted">
                                {{ $res->expires_at ? $res->expires_at->format('Y-m-d H:i') : 'No Expiry' }}
                            </td>
                            <td class="text-end">
                                @if($res->status->value === 'ACTIVE')
                                    @can('release', \Modules\InventoryTransaction\Models\StockReservation::class)
                                        <form method="POST" action="{{ route('inventory.reservations.release', $res->id) }}" class="d-inline" onsubmit="return confirm('Release this active reservation? Stock will be restored to available balance.');">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2">
                                                <i class="bi bi-unlock me-1"></i>Release
                                            </button>
                                        </form>
                                    @endcan
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">No stock reservations found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($reservations->hasPages())
        <div class="card-footer bg-white py-2">
            {{ $reservations->withQueryString()->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection
