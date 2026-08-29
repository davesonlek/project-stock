@extends('layouts.app')

@section('page_title', 'Stock Movement Ledger')

@section('content')
<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <div>
            <h6 class="mb-0 fw-bold"><i class="bi bi-arrow-left-right me-2 text-primary"></i>Stock Movement Ledger (Immutable History)</h6>
            <small class="text-muted">Append-only chronological audit log of all inventory balance mutations</small>
        </div>
        <span class="badge bg-light text-dark border"><i class="bi bi-lock-fill me-1 text-secondary"></i>Immutable</span>
    </div>
    <div class="card-body border-bottom bg-light py-2">
        <form method="GET" action="{{ route('inventory.movements') }}" class="row g-2 align-items-center">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search SKU, product..." value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <select name="movement_type" class="form-select form-select-sm">
                    <option value="">All Movement Types</option>
                    <option value="RECEIVE" {{ request('movement_type') === 'RECEIVE' ? 'selected' : '' }}>RECEIVE (+)</option>
                    <option value="ISSUE" {{ request('movement_type') === 'ISSUE' ? 'selected' : '' }}>ISSUE (-)</option>
                    <option value="TRANSFER_OUT" {{ request('movement_type') === 'TRANSFER_OUT' ? 'selected' : '' }}>TRANSFER_OUT (-)</option>
                    <option value="TRANSFER_IN" {{ request('movement_type') === 'TRANSFER_IN' ? 'selected' : '' }}>TRANSFER_IN (+)</option>
                    <option value="ADJUST_IN" {{ request('movement_type') === 'ADJUST_IN' ? 'selected' : '' }}>ADJUST_IN (+)</option>
                    <option value="ADJUST_OUT" {{ request('movement_type') === 'ADJUST_OUT' ? 'selected' : '' }}>ADJUST_OUT (-)</option>
                    <option value="REVERSAL" {{ request('movement_type') === 'REVERSAL' ? 'selected' : '' }}>REVERSAL (+/-)</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="warehouse_id" class="form-select form-select-sm">
                    <option value="">All Warehouses</option>
                    @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}" {{ request('warehouse_id') == $wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}" title="Date From">
            </div>
            <div class="col-md-2">
                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}" title="Date To">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-sm btn-secondary w-100"><i class="bi bi-search"></i></button>
            </div>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Timestamp</th>
                        <th>Document</th>
                        <th>Movement Type</th>
                        <th>Goods / SKU</th>
                        <th>Warehouse / Loc</th>
                        <th>Lot No</th>
                        <th class="text-end">Quantity Delta</th>
                        <th>Performed By</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($movements as $m)
                        <tr>
                            <td><code>#{{ $m->id }}</code></td>
                            <td class="small text-muted">{{ $m->created_at->format('Y-m-d H:i:s') }}</td>
                            <td>
                                <a href="{{ route('stock.documents.show', $m->document_id) }}" class="fw-semibold text-decoration-none">
                                    {{ $m->document->document_number ?? ('#' . substr($m->document_id, 0, 8)) }}
                                </a>
                                @if($m->reversal_of)
                                    <div class="small text-danger">Rev of #{{ $m->reversal_of }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                    {{ $m->movement_type->value }}
                                </span>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $m->goods->name ?? '-' }}</div>
                                <small class="text-muted">{{ $m->goods->sku ?? '' }}</small>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">{{ $m->warehouse->code ?? '-' }}</span>
                                <span class="small text-muted">&bull; {{ $m->location->code ?? '-' }}</span>
                            </td>
                            <td>
                                @if($m->stockLot)
                                    <span class="badge bg-dark font-monospace">{{ $m->stockLot->lot_no }}</span>
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            </td>
                            <td class="text-end fw-bold {{ (float)$m->quantity_delta >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ (float)$m->quantity_delta >= 0 ? '+' : '' }}{{ number_format((float)$m->quantity_delta, 2) }}
                            </td>
                            <td class="small text-muted">{{ $m->performer->username ?? 'System' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">No stock movements found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($movements->hasPages())
        <div class="card-footer bg-white py-2">
            {{ $movements->withQueryString()->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection
