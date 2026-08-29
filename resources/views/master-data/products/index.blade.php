@extends('layouts.app')

@section('page_title', 'Products')

@section('content')
<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h6 class="mb-0 fw-bold"><i class="bi bi-box me-2 text-primary"></i>Products</h6>
        @can('create', \Modules\MasterData\Models\Product::class)
            <a href="{{ route('products.create') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-circle me-1"></i>Add Product
            </a>
        @endcan
    </div>
    <div class="card-body border-bottom bg-light py-2">
        <form method="GET" action="{{ route('products.index') }}" class="row g-2 align-items-center">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search SKU, barcode, name..." value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <select name="category_id" class="form-select form-select-sm">
                    <option value="">All Categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="brand_id" class="form-select form-select-sm">
                    <option value="">All Brands</option>
                    @foreach($brands as $brand)
                        <option value="{{ $brand->id }}" {{ request('brand_id') == $brand->id ? 'selected' : '' }}>{{ $brand->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="is_active" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>Active</option>
                    <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-sm btn-secondary w-100"><i class="bi bi-search"></i></button>
            </div>
            <div class="col-md-2">
                <a href="{{ route('products.index') }}" class="btn btn-sm btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>SKU</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Brand</th>
                        <th>SKUs (Goods)</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                        <tr>
                            <td class="fw-semibold text-primary">
                                <a href="{{ route('products.show', $product->id) }}" class="text-decoration-none">{{ $product->sku }}</a>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $product->name }}</div>
                                @if($product->barcode)
                                    <small class="text-muted"><i class="bi bi-upc me-1"></i>{{ $product->barcode }}</small>
                                @endif
                            </td>
                            <td>{{ $product->category->name ?? '-' }}</td>
                            <td>{{ $product->brand->name ?? '-' }}</td>
                            <td>
                                <span class="badge bg-light text-dark border">{{ $product->goods_count ?? $product->goods()->count() }} variants</span>
                            </td>
                            <td>
                                @if($product->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-secondary">Inactive</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('products.show', $product->id) }}" class="btn btn-sm btn-outline-info py-0 px-2 me-1" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @can('update', $product)
                                    <a href="{{ route('products.edit', $product->id) }}" class="btn btn-sm btn-outline-primary py-0 px-2 me-1" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    @if($product->is_active)
                                        <form method="POST" action="{{ route('products.deactivate', $product->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2" title="Deactivate">
                                                <i class="bi bi-slash-circle"></i>
                                            </button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('products.activate', $product->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-success py-0 px-2" title="Activate">
                                                <i class="bi bi-check-circle"></i>
                                            </button>
                                        </form>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">No products found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($products->hasPages())
        <div class="card-footer bg-white py-2">
            {{ $products->withQueryString()->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection
