@extends('layouts.app')

@section('page_title', 'Stock Documents')

@section('content')
<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h6 class="mb-0 fw-bold"><i class="bi bi-journal-text me-2 text-primary"></i>Stock Documents & Transactions</h6>
        @can('create', \Modules\InventoryTransaction\Models\StockDocument::class)
            <div class="dropdown">
                <button class="btn btn-sm btn-primary dropdown-toggle" type="button" id="createDocDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-plus-circle me-1"></i>New Transaction
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="createDocDropdown">
                    <li><a class="dropdown-item" href="{{ route('stock.documents.create', ['type' => 'RECEIVE']) }}"><i class="bi bi-box-arrow-in-down text-success me-2"></i>Receive Stock</a></li>
                    <li><a class="dropdown-item" href="{{ route('stock.documents.create', ['type' => 'ISSUE']) }}"><i class="bi bi-box-arrow-up text-warning me-2"></i>Issue Stock</a></li>
                    <li><a class="dropdown-item" href="{{ route('stock.documents.create', ['type' => 'TRANSFER']) }}"><i class="bi bi-arrow-left-right text-info me-2"></i>Transfer Stock</a></li>
                    <li><a class="dropdown-item" href="{{ route('stock.documents.create', ['type' => 'ADJUSTMENT']) }}"><i class="bi bi-sliders text-secondary me-2"></i>Adjust Stock</a></li>
                </ul>
            </div>
        @endcan
    </div>
    <div class="card-body border-bottom bg-light py-2">
        <form method="GET" action="{{ route('stock.documents.index') }}" class="row g-2 align-items-center">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search doc no, remarks..." value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <select name="document_type" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    <option value="RECEIVE" {{ request('document_type') === 'RECEIVE' ? 'selected' : '' }}>RECEIVE</option>
                    <option value="ISSUE" {{ request('document_type') === 'ISSUE' ? 'selected' : '' }}>ISSUE</option>
                    <option value="TRANSFER" {{ request('document_type') === 'TRANSFER' ? 'selected' : '' }}>TRANSFER</option>
                    <option value="ADJUSTMENT" {{ request('document_type') === 'ADJUSTMENT' ? 'selected' : '' }}>ADJUSTMENT</option>
                    <option value="REVERSAL" {{ request('document_type') === 'REVERSAL' ? 'selected' : '' }}>REVERSAL</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="DRAFT" {{ request('status') === 'DRAFT' ? 'selected' : '' }}>DRAFT</option>
                    <option value="PENDING" {{ request('status') === 'PENDING' ? 'selected' : '' }}>PENDING</option>
                    <option value="APPROVED" {{ request('status') === 'APPROVED' ? 'selected' : '' }}>APPROVED</option>
                    <option value="POSTED" {{ request('status') === 'POSTED' ? 'selected' : '' }}>POSTED</option>
                    <option value="REVERSED" {{ request('status') === 'REVERSED' ? 'selected' : '' }}>REVERSED</option>
                    <option value="CANCELLED" {{ request('status') === 'CANCELLED' ? 'selected' : '' }}>CANCELLED</option>
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
            <div class="col-md-1">
                <button type="submit" class="btn btn-sm btn-secondary w-100"><i class="bi bi-search"></i></button>
            </div>
            <div class="col-md-2">
                <a href="{{ route('stock.documents.index') }}" class="btn btn-sm btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Document No</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Source / Destination</th>
                        <th>Created By</th>
                        <th>Created At</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($documents as $doc)
                        <tr>
                            <td>
                                <a href="{{ route('stock.documents.show', $doc->id) }}" class="fw-bold text-decoration-none">
                                    {{ $doc->document_number }}
                                </a>
                                @if($doc->reversal_of)
                                    <div class="small text-muted">Rev of #{{ substr($doc->reversal_of, 0, 8) }}</div>
                                @endif
                            </td>
                            <td>
                                @if($doc->document_type->value === 'RECEIVE')
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">RECEIVE</span>
                                @elseif($doc->document_type->value === 'ISSUE')
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle">ISSUE</span>
                                @elseif($doc->document_type->value === 'TRANSFER')
                                    <span class="badge bg-info-subtle text-info border border-info-subtle">TRANSFER</span>
                                @elseif($doc->document_type->value === 'ADJUSTMENT')
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">ADJUSTMENT</span>
                                @elseif($doc->document_type->value === 'REVERSAL')
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">REVERSAL</span>
                                @endif
                            </td>
                            <td>
                                @if($doc->status->value === 'DRAFT')
                                    <span class="badge bg-secondary">DRAFT</span>
                                @elseif($doc->status->value === 'PENDING')
                                    <span class="badge bg-warning text-dark">PENDING</span>
                                @elseif($doc->status->value === 'APPROVED')
                                    <span class="badge bg-info text-dark">APPROVED</span>
                                @elseif($doc->status->value === 'POSTED')
                                    <span class="badge bg-success">POSTED</span>
                                @elseif($doc->status->value === 'REVERSED')
                                    <span class="badge bg-danger">REVERSED</span>
                                @elseif($doc->status->value === 'CANCELLED')
                                    <span class="badge bg-dark">CANCELLED</span>
                                @endif
                            </td>
                            <td class="small">
                                @if($doc->sourceWarehouse)
                                    <div>From: <strong>{{ $doc->sourceWarehouse->code }}</strong> ({{ $doc->sourceLocation->code ?? 'Loc' }})</div>
                                @endif
                                @if($doc->destinationWarehouse)
                                    <div>To: <strong>{{ $doc->destinationWarehouse->code }}</strong> ({{ $doc->destinationLocation->code ?? 'Loc' }})</div>
                                @endif
                                @if($doc->supplier)
                                    <div>Supplier: <strong>{{ $doc->supplier->name }}</strong></div>
                                @endif
                            </td>
                            <td class="small text-muted">{{ $doc->creator->username ?? '-' }}</td>
                            <td class="small text-muted">{{ $doc->created_at->format('Y-m-d H:i') }}</td>
                            <td class="text-end">
                                <a href="{{ route('stock.documents.show', $doc->id) }}" class="btn btn-sm btn-outline-primary py-0 px-2">
                                    <i class="bi bi-eye me-1"></i>View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">No stock documents found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($documents->hasPages())
        <div class="card-footer bg-white py-2">
            {{ $documents->withQueryString()->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection
