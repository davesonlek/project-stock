@extends('layouts.app')

@section('page_title', 'Create Goods SKU')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-plus-circle me-2 text-primary"></i>Create New Goods (SKU)</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('goods.store') }}">
                    @csrf

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Parent Product <span class="text-danger">*</span></label>
                            <select name="product_id" class="form-select @error('product_id') is-invalid @enderror" required>
                                <option value="">-- Select Product --</option>
                                @foreach($products as $p)
                                    <option value="{{ $p->id }}" {{ old('product_id') == $p->id ? 'selected' : '' }}>{{ $p->name }} ({{ $p->sku }})</option>
                                @endforeach
                            </select>
                            @error('product_id')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Unit of Measure <span class="text-danger">*</span></label>
                            <select name="unit_id" class="form-select @error('unit_id') is-invalid @enderror" required>
                                <option value="">-- Select Unit --</option>
                                @foreach($units as $u)
                                    <option value="{{ $u->id }}" {{ old('unit_id') == $u->id ? 'selected' : '' }}>{{ $u->name }} ({{ $u->code }})</option>
                                @endforeach
                            </select>
                            @error('unit_id')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Pack Size <span class="text-danger">*</span></label>
                            <input type="number" name="pack_size" class="form-control @error('pack_size') is-invalid @enderror" value="{{ old('pack_size', 1) }}" min="1" required>
                            @error('pack_size')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Barcode</label>
                            <input type="text" name="barcode" class="form-control @error('barcode') is-invalid @enderror" value="{{ old('barcode') }}" placeholder="e.g. 8859999888812">
                            @error('barcode')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Cost Price</label>
                            <input type="number" step="0.0001" name="cost_price" class="form-control @error('cost_price') is-invalid @enderror" value="{{ old('cost_price', 0) }}" min="0">
                            @error('cost_price')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Selling Price</label>
                            <input type="number" step="0.0001" name="selling_price" class="form-control @error('selling_price') is-invalid @enderror" value="{{ old('selling_price', 0) }}" min="0">
                            @error('selling_price')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Tracking Capabilities -->
                    <div class="card bg-light border p-3 mb-4">
                        <h6 class="fw-bold mb-2">Inventory Tracking Capabilities</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_lot_tracked" value="1" id="is_lot_tracked" {{ old('is_lot_tracked') ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold" for="is_lot_tracked">
                                        Lot / Batch Tracked
                                    </label>
                                    <div class="small text-muted">Requires lot number and expiration dates during receiving and issue.</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_serial_tracked" value="1" id="is_serial_tracked" {{ old('is_serial_tracked') ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold" for="is_serial_tracked">
                                        Serial Number Tracked
                                    </label>
                                    <div class="small text-muted">Requires unique piece-level serial numbers.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('goods.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">Create Goods SKU</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
