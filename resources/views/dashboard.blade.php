@extends('layouts.app')

@section('page_title', 'Dashboard')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="fw-bold mb-1">Inventory Dashboard</h4>
            <p class="text-muted small mb-0">
                Daily summary date (Asia/Bangkok): <strong>{{ $daily_summary_date ?? now('Asia/Bangkok')->toDateString() }}</strong>
                @if(!empty($kpis['last_refreshed_at']))
                    &bull; Last refreshed: {{ \Carbon\Carbon::parse($kpis['last_refreshed_at'])->timezone('Asia/Bangkok')->format('Y-m-d H:i:s') }} Bangkok
                @else
                    &bull; <span class="text-warning">Summary not refreshed for today</span>
                @endif
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <form method="POST" action="{{ route('dashboard.inventory-summary.refresh') }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh Today Summary
                </button>
            </form>
            <div class="btn-group shadow-sm">
                <a href="{{ route('stock.documents.create', ['type' => 'RECEIVE']) }}" class="btn btn-primary">
                    <i class="bi bi-box-arrow-in-down me-1"></i>New Receive
                </a>
                <a href="{{ route('stock.documents.create', ['type' => 'ISSUE']) }}" class="btn btn-warning text-dark">
                    <i class="bi bi-box-arrow-up me-1"></i>New Issue
                </a>
                <a href="{{ route('stock.documents.create', ['type' => 'TRANSFER']) }}" class="btn btn-info text-dark">
                    <i class="bi bi-arrow-left-right me-1"></i>New Transfer
                </a>
                <a href="{{ route('stock.documents.create', ['type' => 'ADJUSTMENT']) }}" class="btn btn-secondary">
                    <i class="bi bi-sliders me-1"></i>New Adjustment
                </a>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-bold"><i class="bi bi-boxes me-2 text-primary"></i>Current Stock by Unit (live)</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Unit</th>
                            <th class="text-end">On Hand</th>
                            <th class="text-end">Reserved</th>
                            <th class="text-end">Available</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($stock_by_unit as $row)
                            <tr>
                                <td><code>{{ $row->unit_code }}</code></td>
                                <td class="text-end fw-semibold">{{ number_format((float) $row->on_hand, 4) }}</td>
                                <td class="text-end">{{ number_format((float) $row->reserved, 4) }}</td>
                                <td class="text-end text-primary fw-bold">{{ number_format((float) $row->available, 4) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">No stock balance rows for this organization.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        @php
            $movementKpis = [
                ['label' => 'Receive Qty', 'key' => 'receive_qty', 'class' => 'success'],
                ['label' => 'Issue Qty', 'key' => 'issue_qty', 'class' => 'warning'],
                ['label' => 'Transfer In', 'key' => 'transfer_in_qty', 'class' => 'info'],
                ['label' => 'Transfer Out', 'key' => 'transfer_out_qty', 'class' => 'info'],
                ['label' => 'Adjust In', 'key' => 'adjust_in_qty', 'class' => 'secondary'],
                ['label' => 'Adjust Out', 'key' => 'adjust_out_qty', 'class' => 'secondary'],
                ['label' => 'Reversal Net', 'key' => 'reversal_net_qty', 'class' => 'dark'],
                ['label' => 'Net Movement', 'key' => 'net_movement_qty', 'class' => 'primary'],
            ];
        @endphp
        @foreach($movementKpis as $kpi)
            <div class="col-6 col-md-3 col-xl-2">
                <div class="card shadow-sm border-0 h-100 p-3">
                    <span class="text-muted small text-uppercase fw-semibold">{{ $kpi['label'] }}</span>
                    <h5 class="fw-bold mb-0 text-{{ $kpi['class'] }}">{{ number_format((float) ($kpis[$kpi['key']] ?? 0), 4) }}</h5>
                </div>
            </div>
        @endforeach
        <div class="col-6 col-md-3 col-xl-2">
            <div class="card shadow-sm border-0 h-100 p-3">
                <span class="text-muted small text-uppercase fw-semibold">Movement Count</span>
                <h5 class="fw-bold mb-0">{{ $kpis['movement_count'] ?? 0 }}</h5>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-danger h-100 p-3">
                <span class="text-muted small">Reconciliation Mismatches</span>
                <h4 class="fw-bold text-danger mb-0">{{ $kpis['reconciliation_mismatch_count'] ?? 0 }}</h4>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning h-100 p-3">
                <span class="text-muted small">Active Reservations</span>
                <h4 class="fw-bold text-warning mb-0">{{ $kpis['active_reservation_count'] ?? 0 }}</h4>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-danger h-100 p-3">
                <span class="text-muted small">Expired Lots (on hand)</span>
                <h4 class="fw-bold mb-0">{{ $kpis['expired_lot_count'] ?? 0 }}</h4>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-info h-100 p-3">
                <span class="text-muted small">Expiring ≤ 30 Days</span>
                <h4 class="fw-bold text-info mb-0">{{ $kpis['expiring_within_30_days_count'] ?? 0 }}</h4>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-secondary h-100 p-3">
                <span class="text-muted small">Zero Stock Rows</span>
                <h4 class="fw-bold mb-0">{{ $kpis['low_stock_items'] ?? 0 }}</h4>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-primary h-100 p-3">
                <span class="text-muted small">Pending Documents</span>
                <h4 class="fw-bold text-primary mb-0">{{ $kpis['pending_documents'] ?? 0 }}</h4>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white fw-bold">Today's Movement Mix</div>
                <div class="card-body chart-wrap">
                    <canvas id="chartMovementDoughnut" height="220"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white fw-bold">Top 10 Goods (Gross Movement)</div>
                <div class="card-body chart-wrap">
                    <canvas id="chartTopGoods" height="220"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white fw-bold">Gross Movement by Warehouse</div>
                <div class="card-body chart-wrap">
                    <canvas id="chartWarehouseMovement" height="220"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script type="application/json" id="dashboard-chart-data">@json($chart_data ?? [])</script>

    <div class="card shadow-sm mb-4 border-0">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-bold"><i class="bi bi-diagram-3 me-2 text-primary"></i>Stock Document Workflow Status</h6>
        </div>
        <div class="card-body">
            <div class="row g-2 text-center">
                @foreach(['DRAFT' => 'secondary', 'PENDING' => 'warning', 'APPROVED' => 'info', 'POSTED' => 'success', 'REVERSED' => 'danger', 'CANCELLED' => 'dark'] as $status => $badge)
                    <div class="col-4 col-md-2">
                        <div class="p-3 bg-light rounded border">
                            <span class="badge bg-{{ $badge }} {{ $status === 'PENDING' ? 'text-dark' : '' }} mb-2">{{ $status }}</span>
                            <h4 class="fw-bold mb-0">{{ $document_statuses[$status] ?? 0 }}</h4>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2 text-primary"></i>Recent Stock Movements</h6>
            <a href="{{ route('inventory.movements') }}" class="btn btn-sm btn-outline-primary">View Full Ledger</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Time</th>
                            <th>Document</th>
                            <th>Type</th>
                            <th>Goods / SKU</th>
                            <th>Warehouse / Loc</th>
                            <th class="text-end">Quantity Delta</th>
                            <th>User</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recent_movements as $movement)
                            <tr>
                                <td class="small text-muted">{{ $movement->created_at->format('Y-m-d H:i:s') }}</td>
                                <td>
                                    <a href="{{ route('stock.documents.show', $movement->document_id) }}" class="fw-semibold text-decoration-none">
                                        #{{ $movement->document_id }}
                                    </a>
                                </td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                        {{ $movement->movement_type->value }}
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $movement->goods->name ?? 'Goods' }}</div>
                                    <small class="text-muted">{{ $movement->goods->sku ?? '' }}</small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">{{ $movement->warehouse->code ?? '' }}</span>
                                    <span class="small text-muted">&bull; {{ $movement->location->code ?? '' }}</span>
                                </td>
                                <td class="text-end fw-bold {{ (float)$movement->quantity_delta >= 0 ? 'text-success' : 'text-danger' }}">
                                    {{ (float)$movement->quantity_delta >= 0 ? '+' : '' }}{{ number_format((float)$movement->quantity_delta, 4) }}
                                </td>
                                <td class="small text-muted">{{ $movement->performer->username ?? 'System' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox fs-3 d-block mb-1"></i>
                                    No stock movements recorded yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
    @vite('resources/js/dashboard-charts.js')
@endsection
