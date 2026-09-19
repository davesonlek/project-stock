@extends('layouts.app')

@section('page_title', 'Inventory Daily Summary Report')

@section('content')
<div class="card shadow-sm border-0 mb-3">
    <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h6 class="mb-0 fw-bold"><i class="bi bi-calendar3 me-2 text-primary"></i>Inventory Daily Summary</h6>
        @if($lastRefreshedAt)
            <span class="text-muted small">Last refreshed: {{ \Carbon\Carbon::parse($lastRefreshedAt)->timezone('Asia/Bangkok')->format('Y-m-d H:i:s') }} (Bangkok)</span>
        @else
            <span class="text-warning small">No summary data refreshed for the selected range yet.</span>
        @endif
    </div>
    <div class="card-body border-bottom bg-light">
        <form method="GET" action="{{ route('reports.inventory-daily-summary.index') }}" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small mb-1">Date From</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $filters['date_from'] }}" required>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Date To</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $filters['date_to'] }}" required>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Warehouse</label>
                <select name="warehouse_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}" {{ (string) $filters['warehouse_id'] === (string) $wh->id ? 'selected' : '' }}>
                            {{ $wh->code }} — {{ $wh->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Location</label>
                <select name="location_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}" {{ (string) $filters['location_id'] === (string) $loc->id ? 'selected' : '' }}>
                            {{ $loc->code }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Goods</label>
                <select name="goods_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach($goodsItems as $g)
                        <option value="{{ $g->id }}" {{ (string) $filters['goods_id'] === (string) $g->id ? 'selected' : '' }}>
                            {{ $g->sku ?? $g->product?->sku }} — {{ $g->product?->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-secondary flex-grow-1"><i class="bi bi-search me-1"></i>Search</button>
            </div>
        </form>
        <form method="POST" action="{{ route('reports.inventory-daily-summary.refresh') }}" class="row g-2 mt-2 align-items-end" id="refresh-summary-form">
            @csrf
            <input type="hidden" name="date_from" value="{{ $filters['date_from'] }}">
            <input type="hidden" name="date_to" value="{{ $filters['date_to'] }}">
            @if($filters['warehouse_id'])
                <input type="hidden" name="warehouse_id" value="{{ $filters['warehouse_id'] }}">
            @endif
            @if($filters['location_id'])
                <input type="hidden" name="location_id" value="{{ $filters['location_id'] }}">
            @endif
            @if($filters['goods_id'])
                <input type="hidden" name="goods_id" value="{{ $filters['goods_id'] }}">
            @endif
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary" id="refresh-summary-btn">
                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh Summary
                </button>
            </div>
            <div class="col">
                <span class="text-muted small">Recalculates summary from the immutable movement ledger for the selected date range (current organization only).</span>
            </div>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Warehouse</th>
                        <th>Location</th>
                        <th>SKU</th>
                        <th>Goods Name</th>
                        <th class="text-end">Opening</th>
                        <th class="text-end">Receive</th>
                        <th class="text-end">Issue</th>
                        <th class="text-end">Trf In</th>
                        <th class="text-end">Trf Out</th>
                        <th class="text-end">Adj In</th>
                        <th class="text-end">Adj Out</th>
                        <th class="text-end">Rev Net</th>
                        <th class="text-end">Net Mov</th>
                        <th class="text-end">Closing</th>
                        <th class="text-end">Mvt #</th>
                        <th>Refreshed At</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($summaries as $row)
                        <tr>
                            <td>{{ $row->summary_date->format('Y-m-d') }}</td>
                            <td><code>{{ $row->warehouse?->code }}</code></td>
                            <td><code>{{ $row->location?->code }}</code></td>
                            <td>{{ $row->goods?->sku ?? $row->goods?->product?->sku }}</td>
                            <td>{{ $row->goods?->product?->name }}</td>
                            <td class="text-end">{{ number_format((float) $row->opening_qty, 4) }}</td>
                            <td class="text-end">{{ number_format((float) $row->receive_qty, 4) }}</td>
                            <td class="text-end">{{ number_format((float) $row->issue_qty, 4) }}</td>
                            <td class="text-end">{{ number_format((float) $row->transfer_in_qty, 4) }}</td>
                            <td class="text-end">{{ number_format((float) $row->transfer_out_qty, 4) }}</td>
                            <td class="text-end">{{ number_format((float) $row->adjust_in_qty, 4) }}</td>
                            <td class="text-end">{{ number_format((float) $row->adjust_out_qty, 4) }}</td>
                            <td class="text-end">{{ number_format((float) $row->reversal_net_qty, 4) }}</td>
                            <td class="text-end fw-semibold">{{ number_format((float) $row->net_movement_qty, 4) }}</td>
                            <td class="text-end fw-bold">{{ number_format((float) $row->closing_qty, 4) }}</td>
                            <td class="text-end">{{ $row->movement_count }}</td>
                            <td class="small text-muted">{{ $row->refreshed_at?->timezone('Asia/Bangkok')->format('Y-m-d H:i:s') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="17" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                No daily summary rows for this filter. Adjust dates or click <strong>Refresh Summary</strong> to calculate from stock movements.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($summaries->hasPages())
        <div class="card-footer bg-white">
            {{ $summaries->links() }}
        </div>
    @endif
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('refresh-summary-form');
        const btn = document.getElementById('refresh-summary-btn');
        if (form && btn) {
            form.addEventListener('submit', function () {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Refreshing...';
            });
        }
    });
</script>
@endsection
