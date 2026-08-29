@extends('layouts.app')

@section('page_title', 'Edit Goods SKU')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-pencil me-2 text-primary"></i>Edit Goods: {{ $goods->sku }}</h6>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-light border mb-4">
                    <div class="row small">
                        <div class="col-md-6"><strong>Product:</strong> {{ $goods->product->name ?? '-' }}</div>
                        <div class="col-md-6"><strong>Unit:</strong> {{ $goods->unit->name ?? '-' }} (Pack: {{ $goods->pack_size }})</div>
                    </div>
                </div>

                <form method="POST" action="{{ route('goods.update', $goods->id) }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Barcode</label>
                        <input type="text" name="barcode" class="form-control @error('barcode') is-invalid @enderror" value="{{ old('barcode', $goods->barcode) }}">
                        @error('barcode')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Cost Price</label>
                            <input type="number" step="0.0001" name="cost_price" class="form-control @error('cost_price') is-invalid @enderror" value="{{ old('cost_price', $goods->cost_price) }}" min="0">
                            @error('cost_price')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Selling Price</label>
                            <input type="number" step="0.0001" name="selling_price" class="form-control @error('selling_price') is-invalid @enderror" value="{{ old('selling_price', $goods->selling_price) }}" min="0">
                            @error('selling_price')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('goods.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">Update Goods</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
