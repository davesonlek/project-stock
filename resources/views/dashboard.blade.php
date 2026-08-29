@extends('layouts.app')

@section('page_title', 'Dashboard')

@section('content')
<div class="container-fluid px-0">
    <!-- Quick Transaction Actions -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">Inventory Dashboard</h4>
            <p class="text-muted small mb-0">Overview of warehouse balances, reservations, and transactions</p>
        </div>
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

    <!-- KPI Cards Row 1: Stock Quantities -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card kpi-card border-primary h-100 p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Total Available</span>
                        <h3 class="fw-bold my-1 text-primary">{{ number_format((float)$kpis['total_available'], 2) }}</h3>
                        <small class="text-muted">On Hand minus Reserved</small>
                    </div>
                    <div class="kpi-icon bg-primary-subtle text-primary">
                        <i class="bi bi-check2-circle"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card kpi-card border-info h-100 p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Total On Hand</span>
                        <h3 class="fw-bold my-1 text-dark">{{ number_format((float)$kpis['total_on_hand'], 2) }}</h3>
                        <small class="text-muted">Physical warehouse stock</small>
                    </div>
                    <div class="kpi-icon bg-info-subtle text-info">
                        <i class="bi bi-boxes"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card kpi-card border-warning h-100 p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Total Reserved</span>
                        <h3 class="fw-bold my-1 text-warning">{{ number_format((float)$kpis['total_reserved'], 2) }}</h3>
                        <small class="text-muted">Allocated to pending issues</small>
                    </div>
                    <div class="kpi-icon bg-warning-subtle text-warning">
                        <i class="bi bi-bookmark-check"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card kpi-card border-danger h-100 p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Zero Stock Items</span>
                        <h3 class="fw-bold my-1 text-danger">{{ $kpis['low_stock_items'] }}</h3>
                        <small class="text-muted">Items with on-hand &le; 0</small>
                    </div>
                    <div class="kpi-icon bg-danger-subtle text-danger">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- KPI Cards Row 2: Operations & Lots -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card shadow-sm p-3 border-0 bg-white">
                <div class="d-flex align-items-center">
                    <div class="kpi-icon bg-success-subtle text-success me-3">
                        <i class="bi bi-arrow-down-left-square"></i>
                    </div>
                    <div>
                        <span class="text-muted small">Today's Receive</span>
                        <h4 class="fw-bold mb-0">{{ $kpis['today_receive'] }}</h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card shadow-sm p-3 border-0 bg-white">
                <div class="d-flex align-items-center">
                    <div class="kpi-icon bg-warning-subtle text-warning me-3">
                        <i class="bi bi-arrow-up-right-square"></i>
                    </div>
                    <div>
                        <span class="text-muted small">Today's Issue</span>
                        <h4 class="fw-bold mb-0">{{ $kpis['today_issue'] }}</h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card shadow-sm p-3 border-0 bg-white">
                <div class="d-flex align-items-center">
                    <div class="kpi-icon bg-primary-subtle text-primary me-3">
                        <i class="bi bi-arrow-repeat"></i>
                    </div>
                    <div>
                        <span class="text-muted small">Today's Transfer</span>
                        <h4 class="fw-bold mb-0">{{ $kpis['today_transfer'] }}</h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card shadow-sm p-3 border-0 bg-white">
                <div class="d-flex align-items-center">
                    <div class="kpi-icon bg-secondary-subtle text-secondary me-3">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <div>
                        <span class="text-muted small">Pending Approval</span>
                        <h4 class="fw-bold mb-0 text-primary">{{ $kpis['pending_documents'] }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Document Status Workflow Cards -->
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-bold"><i class="bi bi-diagram-3 me-2 text-primary"></i>Stock Document Workflow Status</h6>
        </div>
        <div class="card-body">
            <div class="row g-2 text-center">
                <div class="col-4 col-md-2">
                    <div class="p-3 bg-light rounded border">
                        <span class="badge bg-secondary mb-2">DRAFT</span>
                        <h4 class="fw-bold mb-0">{{ $document_statuses['DRAFT'] }}</h4>
                    </div>
                </div>
                <div class="col-4 col-md-2">
                    <div class="p-3 bg-light rounded border">
                        <span class="badge bg-warning text-dark mb-2">PENDING</span>
                        <h4 class="fw-bold mb-0 text-warning">{{ $document_statuses['PENDING'] }}</h4>
                    </div>
                </div>
                <div class="col-4 col-md-2">
                    <div class="p-3 bg-light rounded border">
                        <span class="badge bg-info text-dark mb-2">APPROVED</span>
                        <h4 class="fw-bold mb-0 text-info">{{ $document_statuses['APPROVED'] }}</h4>
                    </div>
                </div>
                <div class="col-4 col-md-2">
                    <div class="p-3 bg-light rounded border">
                        <span class="badge bg-success mb-2">POSTED</span>
                        <h4 class="fw-bold mb-0 text-success">{{ $document_statuses['POSTED'] }}</h4>
                    </div>
                </div>
                <div class="col-4 col-md-2">
                    <div class="p-3 bg-light rounded border">
                        <span class="badge bg-danger mb-2">REVERSED</span>
                        <h4 class="fw-bold mb-0 text-danger">{{ $document_statuses['REVERSED'] }}</h4>
                    </div>
                </div>
                <div class="col-4 col-md-2">
                    <div class="p-3 bg-light rounded border">
                        <span class="badge bg-dark mb-2">CANCELLED</span>
                        <h4 class="fw-bold mb-0 text-muted">{{ $document_statuses['CANCELLED'] }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Stock Movements Table -->
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
                                    {{ (float)$movement->quantity_delta >= 0 ? '+' : '' }}{{ number_format((float)$movement->quantity_delta, 2) }}
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
