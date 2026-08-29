@extends('layouts.app')

@section('page_title', 'Product Detail')

@section('content')
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-info-circle me-2 text-primary"></i>Product Info</h6>
                @can('update', $product)
                    <a href="{{ route('products.edit', $product->id) }}" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-pencil me-1"></i>Edit
                    </a>
                @endcan
            </div>
            <div class="card-body">
                <h5 class="fw-bold mb-1">{{ $product->name }}</h5>
                <p class="text-muted small mb-3"><i class="bi bi-tag me-1"></i>SKU: <strong class="text-dark">{{ $product->sku }}</strong></p>

                <table class="table table-sm table-borderless small mb-0">
                    <tr>
                        <td class="text-muted" style="width: 40%;">Barcode:</td>
                        <td class="fw-semibold">{{ $product->barcode ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Category:</td>
                        <td>{{ $product->category->name ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Brand:</td>
                        <td>{{ $product->brand->name ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Status:</td>
                        <td>
                            @if($product->is_active)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-secondary">Inactive</span>
                            @endif
                        </td>
                    </tr>
                </table>

                @if($product->description)
                    <hr class="my-3">
                    <h6 class="small fw-bold text-muted text-uppercase mb-1">Description</h6>
                    <p class="small text-secondary mb-0">{{ $product->description }}</p>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-grid me-2 text-primary"></i>Goods (SKUs & Packaging)</h6>
                @can('create', \Modules\MasterData\Models\Goods::class)
                    <a href="{{ route('goods.create') }}" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus-circle me-1"></i>Add Goods SKU
                    </a>
                @endcan
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>SKU</th>
                                <th>Unit / Pack Size</th>
                                <th>Tracking Mode</th>
                                <th>Cost / Sell Price</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($product->goods as $g)
                                <tr>
                                    <td class="fw-semibold text-primary">{{ $g->sku }}</td>
                                    <td>
                                        <div>{{ $g->unit->name ?? 'Unit' }}</div>
                                        <small class="text-muted">Pack: {{ $g->pack_size }}</small>
                                    </td>
                                    <td>
                                        @if($g->is_lot_tracked && $g->is_serial_tracked)
                                            <span class="badge bg-purple text-white" style="background-color: #7c3aed;">LOT + SERIAL</span>
                                        @elseif($g->is_lot_tracked)
                                            <span class="badge bg-warning text-dark">LOT</span>
                                        @elseif($g->is_serial_tracked)
                                            <span class="badge bg-info text-dark">SERIAL</span>
                                        @else
                                            <span class="badge bg-light text-dark border">STANDARD</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div>${{ number_format((float)($g->cost_price ?? 0), 2) }}</div>
                                        <small class="text-muted">${{ number_format((float)($g->selling_price ?? 0), 2) }}</small>
                                    </td>
                                    <td>
                                        @if($g->is_active)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-secondary">Inactive</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @can('update', $g)
                                            <a href="{{ route('goods.edit', $g->id) }}" class="btn btn-sm btn-outline-primary py-0 px-2">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No goods (SKUs) registered for this product.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
