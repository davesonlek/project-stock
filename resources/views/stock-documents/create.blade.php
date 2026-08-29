@extends('layouts.app')

@section('page_title', 'New ' . $type . ' Document')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold">
                        @if($type === 'RECEIVE')
                            <i class="bi bi-box-arrow-in-down text-success me-2"></i>Create New Stock Receive (Draft)
                        @elseif($type === 'ISSUE')
                            <i class="bi bi-box-arrow-up text-warning me-2"></i>Create New Stock Issue (Draft)
                        @elseif($type === 'TRANSFER')
                            <i class="bi bi-arrow-left-right text-info me-2"></i>Create New Stock Transfer (Draft)
                        @elseif($type === 'ADJUSTMENT')
                            <i class="bi bi-sliders text-secondary me-2"></i>Create New Stock Adjustment (Draft)
                        @endif
                    </h6>
                    <div class="btn-group btn-group-sm">
                        <a href="{{ route('stock.documents.create', ['type' => 'RECEIVE']) }}" class="btn btn-outline-success {{ $type === 'RECEIVE' ? 'active' : '' }}">Receive</a>
                        <a href="{{ route('stock.documents.create', ['type' => 'ISSUE']) }}" class="btn btn-outline-warning text-dark {{ $type === 'ISSUE' ? 'active' : '' }}">Issue</a>
                        <a href="{{ route('stock.documents.create', ['type' => 'TRANSFER']) }}" class="btn btn-outline-info text-dark {{ $type === 'TRANSFER' ? 'active' : '' }}">Transfer</a>
                        <a href="{{ route('stock.documents.create', ['type' => 'ADJUSTMENT']) }}" class="btn btn-outline-secondary {{ $type === 'ADJUSTMENT' ? 'active' : '' }}">Adjustment</a>
                    </div>
                </div>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('stock.documents.store') }}">
                    @csrf
                    <input type="hidden" name="document_type" value="{{ $type }}">

                    @if($type === 'RECEIVE')
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Destination Warehouse <span class="text-danger">*</span></label>
                                <select name="destination_warehouse_id" id="dest_wh" class="form-select @error('destination_warehouse_id') is-invalid @enderror" required onchange="filterLocations('dest_wh', 'dest_loc')">
                                    <option value="">-- Select Destination Warehouse --</option>
                                    @foreach($warehouses as $wh)
                                        <option value="{{ $wh->id }}" {{ old('destination_warehouse_id') == $wh->id ? 'selected' : '' }}>{{ $wh->name }} ({{ $wh->code }})</option>
                                    @endforeach
                                </select>
                                @error('destination_warehouse_id')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Destination Location <span class="text-danger">*</span></label>
                                <select name="destination_location_id" id="dest_loc" class="form-select @error('destination_location_id') is-invalid @enderror" required>
                                    <option value="">-- Select Location --</option>
                                    @foreach($warehouses as $wh)
                                        @foreach($wh->locations as $loc)
                                            <option value="{{ $loc->id }}" data-warehouse="{{ $wh->id }}" {{ old('destination_location_id') == $loc->id ? 'selected' : '' }}>
                                                {{ $wh->code }}: {{ $loc->code }} ({{ $loc->name }})
                                            </option>
                                        @endforeach
                                    @endforeach
                                </select>
                                @error('destination_location_id')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Supplier (Optional)</label>
                            <select name="supplier_id" class="form-select @error('supplier_id') is-invalid @enderror">
                                <option value="">-- Select Supplier --</option>
                                @foreach($suppliers as $sup)
                                    <option value="{{ $sup->id }}" {{ old('supplier_id') == $sup->id ? 'selected' : '' }}>{{ $sup->name }} ({{ $sup->code }})</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if($type === 'ISSUE')
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Source Warehouse <span class="text-danger">*</span></label>
                                <select name="source_warehouse_id" id="src_wh" class="form-select @error('source_warehouse_id') is-invalid @enderror" required onchange="filterLocations('src_wh', 'src_loc')">
                                    <option value="">-- Select Source Warehouse --</option>
                                    @foreach($warehouses as $wh)
                                        <option value="{{ $wh->id }}" {{ old('source_warehouse_id') == $wh->id ? 'selected' : '' }}>{{ $wh->name }} ({{ $wh->code }})</option>
                                    @endforeach
                                </select>
                                @error('source_warehouse_id')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Source Location <span class="text-danger">*</span></label>
                                <select name="source_location_id" id="src_loc" class="form-select @error('source_location_id') is-invalid @enderror" required>
                                    <option value="">-- Select Location --</option>
                                    @foreach($warehouses as $wh)
                                        @foreach($wh->locations as $loc)
                                            <option value="{{ $loc->id }}" data-warehouse="{{ $wh->id }}" {{ old('source_location_id') == $loc->id ? 'selected' : '' }}>
                                                {{ $wh->code }}: {{ $loc->code }} ({{ $loc->name }})
                                            </option>
                                        @endforeach
                                    @endforeach
                                </select>
                                @error('source_location_id')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    @endif

                    @if($type === 'TRANSFER')
                        <!-- Source Warehouse / Location -->
                        <div class="card bg-light border p-3 mb-3">
                            <h6 class="fw-bold mb-2 text-primary"><i class="bi bi-box-arrow-up me-1"></i>Origin (Source)</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Source Warehouse <span class="text-danger">*</span></label>
                                    <select name="source_warehouse_id" id="src_wh" class="form-select @error('source_warehouse_id') is-invalid @enderror" required onchange="filterLocations('src_wh', 'src_loc')">
                                        <option value="">-- Select Source Warehouse --</option>
                                        @foreach($warehouses as $wh)
                                            <option value="{{ $wh->id }}" {{ old('source_warehouse_id') == $wh->id ? 'selected' : '' }}>{{ $wh->name }} ({{ $wh->code }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Source Location <span class="text-danger">*</span></label>
                                    <select name="source_location_id" id="src_loc" class="form-select @error('source_location_id') is-invalid @enderror" required>
                                        <option value="">-- Select Location --</option>
                                        @foreach($warehouses as $wh)
                                            @foreach($wh->locations as $loc)
                                                <option value="{{ $loc->id }}" data-warehouse="{{ $wh->id }}" {{ old('source_location_id') == $loc->id ? 'selected' : '' }}>
                                                    {{ $wh->code }}: {{ $loc->code }}
                                                </option>
                                            @endforeach
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Destination Warehouse / Location -->
                        <div class="card bg-light border p-3 mb-3">
                            <h6 class="fw-bold mb-2 text-success"><i class="bi bi-box-arrow-in-down me-1"></i>Destination</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Destination Warehouse <span class="text-danger">*</span></label>
                                    <select name="destination_warehouse_id" id="dest_wh" class="form-select @error('destination_warehouse_id') is-invalid @enderror" required onchange="filterLocations('dest_wh', 'dest_loc')">
                                        <option value="">-- Select Destination Warehouse --</option>
                                        @foreach($warehouses as $wh)
                                            <option value="{{ $wh->id }}" {{ old('destination_warehouse_id') == $wh->id ? 'selected' : '' }}>{{ $wh->name }} ({{ $wh->code }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Destination Location <span class="text-danger">*</span></label>
                                    <select name="destination_location_id" id="dest_loc" class="form-select @error('destination_location_id') is-invalid @enderror" required>
                                        <option value="">-- Select Location --</option>
                                        @foreach($warehouses as $wh)
                                            @foreach($wh->locations as $loc)
                                                <option value="{{ $loc->id }}" data-warehouse="{{ $wh->id }}" {{ old('destination_location_id') == $loc->id ? 'selected' : '' }}>
                                                    {{ $wh->code }}: {{ $loc->code }}
                                                </option>
                                            @endforeach
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if($type === 'ADJUSTMENT')
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Warehouse <span class="text-danger">*</span></label>
                                <select name="source_warehouse_id" id="src_wh" class="form-select @error('source_warehouse_id') is-invalid @enderror" required onchange="filterLocations('src_wh', 'src_loc')">
                                    <option value="">-- Select Warehouse --</option>
                                    @foreach($warehouses as $wh)
                                        <option value="{{ $wh->id }}" {{ old('source_warehouse_id') == $wh->id ? 'selected' : '' }}>{{ $wh->name }} ({{ $wh->code }})</option>
                                    @endforeach
                                </select>
                                @error('source_warehouse_id')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Location <span class="text-danger">*</span></label>
                                <select name="source_location_id" id="src_loc" class="form-select @error('source_location_id') is-invalid @enderror" required>
                                    <option value="">-- Select Location --</option>
                                    @foreach($warehouses as $wh)
                                        @foreach($wh->locations as $loc)
                                            <option value="{{ $loc->id }}" data-warehouse="{{ $wh->id }}" {{ old('source_location_id') == $loc->id ? 'selected' : '' }}>
                                                {{ $wh->code }}: {{ $loc->code }}
                                            </option>
                                        @endforeach
                                    @endforeach
                                </select>
                                @error('source_location_id')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    @endif

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Remarks / Reason</label>
                        <textarea name="remarks" class="form-control" rows="3" placeholder="Reference PO/SO, audit reason, or transaction notes...">{{ old('remarks') }}</textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('stock.documents.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">Create Draft Document</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function filterLocations(whSelectId, locSelectId) {
    const whSelect = document.getElementById(whSelectId);
    const locSelect = document.getElementById(locSelectId);
    if (!whSelect || !locSelect) return;

    const selectedWh = whSelect.value;
    Array.from(locSelect.options).forEach(opt => {
        if (!opt.value) return;
        const optWh = opt.getAttribute('data-warehouse');
        opt.style.display = (!selectedWh || optWh === selectedWh) ? '' : 'none';
    });
}
document.addEventListener('DOMContentLoaded', () => {
    filterLocations('src_wh', 'src_loc');
    filterLocations('dest_wh', 'dest_loc');
});
</script>
@endsection
