@extends('layouts.app')

@section('page_title', 'Document #' . $document->document_number)

@section('content')
<div class="container-fluid px-0">
    <!-- Header Card -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <div class="d-flex align-items-center gap-3">
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-file-earmark-text text-primary me-2"></i>{{ $document->document_number }}
                </h5>
                @if($document->document_type->value === 'RECEIVE')
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1">RECEIVE</span>
                @elseif($document->document_type->value === 'ISSUE')
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-1">ISSUE</span>
                @elseif($document->document_type->value === 'TRANSFER')
                    <span class="badge bg-info-subtle text-info border border-info-subtle px-3 py-1">TRANSFER</span>
                @elseif($document->document_type->value === 'ADJUSTMENT')
                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-1">ADJUSTMENT</span>
                @elseif($document->document_type->value === 'REVERSAL')
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-1">REVERSAL</span>
                @endif

                @if($document->status->value === 'DRAFT')
                    <span class="badge bg-secondary">DRAFT</span>
                @elseif($document->status->value === 'PENDING')
                    <span class="badge bg-warning text-dark">PENDING APPROVAL</span>
                @elseif($document->status->value === 'APPROVED')
                    <span class="badge bg-info text-dark">APPROVED</span>
                @elseif($document->status->value === 'POSTED')
                    <span class="badge bg-success">POSTED</span>
                @elseif($document->status->value === 'REVERSED')
                    <span class="badge bg-danger">REVERSED</span>
                @elseif($document->status->value === 'CANCELLED')
                    <span class="badge bg-dark">CANCELLED</span>
                @endif
            </div>

            <!-- Action Bar -->
            <div class="d-flex gap-2 align-items-center">
                @if($document->status->value === 'DRAFT')
                    @can('submit', $document)
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#submitModal">
                            <i class="bi bi-send me-1"></i>Submit Document
                        </button>
                    @endcan
                    @can('cancel', $document)
                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelModal">
                            <i class="bi bi-x-circle me-1"></i>Cancel
                        </button>
                    @endcan
                @elseif($document->status->value === 'PENDING')
                    @can('approve', $document)
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#approveModal">
                            <i class="bi bi-check2-circle me-1"></i>Approve Document
                        </button>
                    @endcan
                    @can('cancel', $document)
                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelModal">
                            <i class="bi bi-x-circle me-1"></i>Cancel
                        </button>
                    @endcan
                @elseif($document->status->value === 'APPROVED')
                    @can('post', $document)
                        <button type="button" class="btn btn-success btn-action-lg" data-bs-toggle="modal" data-bs-target="#postModal">
                            <i class="bi bi-lightning-charge me-1"></i>POST STOCK
                        </button>
                    @endcan
                    @can('cancel', $document)
                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelModal">
                            <i class="bi bi-x-circle me-1"></i>Cancel
                        </button>
                    @endcan
                @elseif($document->status->value === 'POSTED')
                    @can('reverse', $document)
                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#reverseModal">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Reverse Document
                        </button>
                    @endcan
                @endif
            </div>
        </div>

        <div class="card-body bg-light border-bottom">
            <div class="row g-3 small">
                <div class="col-md-3">
                    <span class="text-muted d-block">Source Warehouse:</span>
                    <strong class="text-dark">{{ $document->sourceWarehouse->name ?? '-' }} ({{ $document->sourceLocation->code ?? '-' }})</strong>
                </div>
                <div class="col-md-3">
                    <span class="text-muted d-block">Destination Warehouse:</span>
                    <strong class="text-dark">{{ $document->destinationWarehouse->name ?? '-' }} ({{ $document->destinationLocation->code ?? '-' }})</strong>
                </div>
                <div class="col-md-3">
                    <span class="text-muted d-block">Supplier:</span>
                    <strong class="text-dark">{{ $document->supplier->name ?? '-' }}</strong>
                </div>
                <div class="col-md-3">
                    <span class="text-muted d-block">Created By:</span>
                    <strong>{{ $document->creator->username ?? '-' }}</strong> on {{ $document->created_at->format('Y-m-d H:i') }}
                </div>
            </div>

            @if($document->submitted_at || $document->approved_at || $document->posted_at)
                <hr class="my-2">
                <div class="row g-3 small text-muted">
                    @if($document->submitted_at)
                        <div class="col-md-4">
                            <span>Submitted by: <strong>{{ $document->submitter->username ?? 'User' }}</strong> ({{ $document->submitted_at->format('Y-m-d H:i') }})</span>
                        </div>
                    @endif
                    @if($document->approved_at)
                        <div class="col-md-4">
                            <span>Approved by: <strong>{{ $document->approver->username ?? 'User' }}</strong> ({{ $document->approved_at->format('Y-m-d H:i') }})</span>
                        </div>
                    @endif
                    @if($document->posted_at)
                        <div class="col-md-4">
                            <span class="text-success">Posted by: <strong>{{ $document->poster->username ?? 'User' }}</strong> ({{ $document->posted_at->format('Y-m-d H:i') }})</span>
                        </div>
                    @endif
                </div>
            @endif

            @if($document->remarks)
                <div class="mt-2 small text-secondary">
                    <strong>Remarks:</strong> {{ $document->remarks }}
                </div>
            @endif

            @if($document->reversal_of)
                <div class="alert alert-warning py-2 px-3 mt-2 mb-0 small">
                    <i class="bi bi-info-circle me-1"></i>This is a compensating reversal document for:
                    <a href="{{ route('stock.documents.show', $document->reversal_of) }}" class="fw-bold">#{{ substr($document->reversal_of, 0, 8) }}</a>
                </div>
            @endif
        </div>
    </div>

    <!-- Document Lines Card -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <h6 class="mb-0 fw-bold"><i class="bi bi-list-check me-2 text-primary"></i>Document Lines</h6>
            <span class="badge bg-light text-dark border">{{ $document->lines->count() }} items</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Goods / SKU</th>
                            <th>Unit & Pack</th>
                            <th class="text-end">{{ $document->document_type->value === 'ADJUSTMENT' ? 'Counted Qty' : 'Quantity' }}</th>
                            <th>Lot Information</th>
                            <th>Serial Numbers</th>
                            @if($document->document_type->value === 'ISSUE')
                                <th>Reservation</th>
                            @endif
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($document->lines as $index => $line)
                            @php
                                $res = $activeReservations[$line->id] ?? null;
                            @endphp
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>
                                    <div class="fw-semibold text-primary">{{ $line->goods->name ?? 'Goods' }}</div>
                                    <small class="text-muted">{{ $line->goods->sku ?? '-' }}</small>
                                </td>
                                <td>
                                    <div>{{ $line->goods->unit->name ?? 'Unit' }}</div>
                                    <small class="text-muted">Pack: {{ $line->goods->pack_size ?? 1 }}</small>
                                </td>
                                <td class="text-end fw-bold fs-6">
                                    @if($document->document_type->value === 'ADJUSTMENT')
                                        {{ number_format((float)$line->counted_quantity, 2) }}
                                    @else
                                        {{ number_format((float)$line->quantity, 2) }}
                                    @endif
                                </td>
                                <td>
                                    @if($line->lot_no || $line->stockLot)
                                        <span class="badge bg-dark font-monospace">{{ $line->lot_no ?? $line->stockLot->lot_no }}</span>
                                        @if($line->expired_at || ($line->stockLot && $line->stockLot->expired_at))
                                            <div class="small text-muted">Exp: {{ $line->expired_at ?? $line->stockLot->expired_at }}</div>
                                        @endif
                                    @else
                                        <span class="text-muted small">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($line->lineSerials && $line->lineSerials->isNotEmpty())
                                        <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" data-bs-toggle="modal" data-bs-target="#serialsModal{{ $line->id }}">
                                            <i class="bi bi-upc me-1"></i>{{ $line->lineSerials->count() }} Serials
                                        </button>
                                        <!-- Modal for serials -->
                                        <div class="modal fade" id="serialsModal{{ $line->id }}" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered modal-sm">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h6 class="modal-title fw-bold">Serials for Line #{{ $index + 1 }}</h6>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <ul class="list-group list-group-flush font-monospace small">
                                                            @foreach($line->lineSerials as $ls)
                                                                <li class="list-group-item">{{ $ls->serial_no }}</li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-muted small">-</span>
                                    @endif
                                </td>
                                @if($document->document_type->value === 'ISSUE')
                                    <td>
                                        @if($res)
                                            <span class="badge bg-warning text-dark"><i class="bi bi-bookmark-check me-1"></i>ACTIVE</span>
                                            <small class="text-muted d-block">{{ number_format((float)$res->quantity, 2) }}</small>
                                        @elseif($document->status->value === 'POSTED')
                                            <span class="badge bg-success">CONSUMED</span>
                                        @else
                                            <span class="badge bg-light text-muted border">NONE</span>
                                        @endif
                                    </td>
                                @endif
                                <td class="text-end">
                                    @if($document->status->value === 'DRAFT')
                                        <form method="POST" action="{{ route('stock.documents.lines.delete', ['id' => $document->id, 'lineId' => $line->id]) }}" class="d-inline" onsubmit="return confirm('Remove this line?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    @elseif(($document->status->value === 'PENDING' || $document->status->value === 'APPROVED') && $document->document_type->value === 'ISSUE' && !$res)
                                        <form method="POST" action="{{ route('stock.documents.reserve', ['id' => $document->id, 'lineId' => $line->id]) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-warning text-dark py-0 px-2" title="Reserve Stock for this Line">
                                                <i class="bi bi-bookmark-plus me-1"></i>Reserve
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-muted small">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <i class="bi bi-box2 fs-3 d-block mb-1"></i>
                                    No items added to this document yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Line Card (Only for DRAFT) -->
    @if($document->status->value === 'DRAFT')
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-plus-circle me-2 text-primary"></i>Add Item to Document</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('stock.documents.lines.add', $document->id) }}">
                    @csrf

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Select Goods (SKU) <span class="text-danger">*</span></label>
                            <select name="goods_id" id="goods_select" class="form-select" required onchange="handleGoodsChange()">
                                <option value="">-- Choose SKU --</option>
                                @foreach($goodsList as $g)
                                    <option value="{{ $g->id }}"
                                        data-lot="{{ $g->is_lot_tracked ? '1' : '0' }}"
                                        data-serial="{{ $g->is_serial_tracked ? '1' : '0' }}"
                                        data-unit="{{ $g->unit->name ?? 'Unit' }}">
                                        {{ $g->name }} ({{ $g->sku }}) - [{{ $g->is_lot_tracked ? 'LOT' : '' }} {{ $g->is_serial_tracked ? 'SERIAL' : '' }}]
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                {{ $document->document_type->value === 'ADJUSTMENT' ? 'Counted Physical Quantity' : 'Transaction Quantity' }} <span class="text-danger">*</span>
                            </label>
                            @if($document->document_type->value === 'ADJUSTMENT')
                                <input type="number" step="0.0001" name="counted_quantity" id="qty_input" class="form-control form-control-lg" value="0" min="0" required>
                                <small class="text-muted">Enter total counted inventory in this location.</small>
                            @else
                                <input type="number" step="0.0001" name="quantity" id="qty_input" class="form-control form-control-lg" value="1" min="0.0001" required oninput="updateSerialHint()">
                            @endif
                        </div>
                    </div>

                    <!-- Lot Tracked Section -->
                    <div id="lot_section" class="card bg-light border p-3 mb-3" style="display: none;">
                        <h6 class="fw-bold mb-2 text-dark"><i class="bi bi-tag me-1"></i>Lot Tracking Details</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Lot Number <span class="text-danger">*</span></label>
                                <input type="text" name="lot_no" id="lot_no" class="form-control form-control-sm" placeholder="e.g. LOT-2026-001">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Manufactured Date</label>
                                <input type="date" name="manufactured_at" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Expiry Date <span class="text-danger">*</span></label>
                                <input type="date" name="expired_at" id="expired_at" class="form-control form-control-sm">
                            </div>
                        </div>
                    </div>

                    <!-- Serial Tracked Section -->
                    <div id="serial_section" class="card bg-light border p-3 mb-3" style="display: none;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-upc-scan me-1"></i>Serial Numbers (1 per line)</h6>
                            <span id="serial_count_badge" class="badge bg-primary">0 Serials entered</span>
                        </div>
                        <textarea name="serials_text" id="serials_text" class="form-control font-monospace" rows="4" placeholder="SN001&#10;SN002&#10;SN003" oninput="updateSerialHint()"></textarea>
                        <small class="text-muted">Enter exactly one serial number per line.</small>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary px-4 no-disable">
                            <i class="bi bi-plus-circle me-1"></i>Add Line
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Stock Movements Table (If POSTED or REVERSED) -->
    @if($movements->isNotEmpty())
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold text-success"><i class="bi bi-arrow-left-right me-2"></i>Executed Stock Movements (Immutable Ledger)</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Movement ID</th>
                                <th>Type</th>
                                <th>Goods</th>
                                <th>Warehouse / Location</th>
                                <th class="text-end">Quantity Delta</th>
                                <th>Performed By</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($movements as $m)
                                <tr>
                                    <td><code>#{{ $m->id }}</code></td>
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
                                        <span>{{ $m->warehouse->code ?? '-' }}</span> &bull; <code>{{ $m->location->code ?? '-' }}</code>
                                    </td>
                                    <td class="text-end fw-bold {{ (float)$m->quantity_delta >= 0 ? 'text-success' : 'text-danger' }}">
                                        {{ (float)$m->quantity_delta >= 0 ? '+' : '' }}{{ number_format((float)$m->quantity_delta, 2) }}
                                    </td>
                                    <td class="small">{{ $m->performer->username ?? 'System' }}</td>
                                    <td class="small text-muted">{{ $m->created_at->format('Y-m-d H:i:s') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <!-- Audit Timeline -->
    @if($auditLogs->isNotEmpty())
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2 text-primary"></i>Audit Trail Timeline</h6>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush small">
                    @foreach($auditLogs as $log)
                        <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                            <div>
                                <span class="badge bg-secondary me-2">{{ $log->action }}</span>
                                <span class="fw-semibold">{{ $log->user->username ?? 'System' }}</span>
                                <span class="text-muted">&bull; {{ $log->created_at->format('Y-m-d H:i:s') }}</span>
                            </div>
                            <span class="text-muted small">IP: {{ $log->ip_address }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif
</div>

<!-- Modal: Submit Document -->
<div class="modal fade" id="submitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('stock.documents.submit', $document->id) }}">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title fw-bold">Submit Document for Approval</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Are you sure you want to submit this document? After submission, the document will enter <strong>PENDING</strong> status and can no longer be edited.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Document</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Approve Document -->
<div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('stock.documents.approve', $document->id) }}">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title fw-bold">Approve Stock Document</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-1">Approve this stock document?</p>
                    <div class="alert alert-info py-2 px-3 small mb-0">
                        <i class="bi bi-info-circle me-1"></i>Stock balances will <strong>NOT change</strong> until the document is explicitly <strong>POSTED</strong>.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve Document</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Cancel Document -->
<div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('stock.documents.cancel', $document->id) }}">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title fw-bold">Cancel Stock Document</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Are you sure you want to cancel this document? Any active reservations will be automatically released.</p>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Cancellation Reason</label>
                        <input type="text" name="reason" class="form-control form-control-sm" required placeholder="e.g. Order cancelled by client">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-danger">Cancel Document</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: POST Document -->
<div class="modal fade" id="postModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('stock.documents.post', $document->id) }}">
                @csrf
                <input type="hidden" name="idempotency_key" value="">
                <div class="modal-header bg-success text-white">
                    <h6 class="modal-title fw-bold"><i class="bi bi-lightning-charge me-1"></i>Confirm Stock Posting</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning py-2 px-3 small">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i><strong>Warning:</strong> Posting will update inventory balances and create immutable stock movements. This action cannot be edited afterward.
                    </div>
                    <p class="mb-0 small">Are you ready to commit this transaction to the physical stock ledger?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold px-4">Confirm &amp; POST</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Reverse Document -->
<div class="modal fade" id="reverseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('stock.documents.reverse', $document->id) }}">
                @csrf
                <input type="hidden" name="idempotency_key" value="">
                <div class="modal-header bg-danger text-white">
                    <h6 class="modal-title fw-bold"><i class="bi bi-arrow-counterclockwise me-1"></i>Reverse Posted Document</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger py-2 px-3 small">
                        <i class="bi bi-shield-exclamation me-1"></i><strong>Notice:</strong> Reversal does NOT delete the original transaction. The system will create compensating stock movements.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Reversal Reason <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="2" required placeholder="State exact reason for reversing this transaction..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger fw-bold px-4">Execute Reversal</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function handleGoodsChange() {
    const sel = document.getElementById('goods_select');
    const lotSec = document.getElementById('lot_section');
    const serialSec = document.getElementById('serial_section');
    if (!sel || !sel.value) {
        if (lotSec) lotSec.style.display = 'none';
        if (serialSec) serialSec.style.display = 'none';
        return;
    }
    const opt = sel.options[sel.selectedIndex];
    const isLot = opt.getAttribute('data-lot') === '1';
    const isSerial = opt.getAttribute('data-serial') === '1';

    if (lotSec) lotSec.style.display = isLot ? 'block' : 'none';
    if (serialSec) serialSec.style.display = isSerial ? 'block' : 'none';
}

function updateSerialHint() {
    const textarea = document.getElementById('serials_text');
    const badge = document.getElementById('serial_count_badge');
    const qtyInput = document.getElementById('qty_input');
    if (!textarea || !badge) return;

    const lines = textarea.value.split(/\r?\n/).filter(line => line.trim().length > 0);
    const count = lines.length;
    badge.innerText = count + ' Serials entered';

    if (qtyInput && count > 0) {
        qtyInput.value = count;
    }
}
</script>
@endsection
