<?php

namespace Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\MasterData\Models\Brand;
use Modules\MasterData\Models\Category;
use Modules\MasterData\Models\Product;
use Modules\MasterData\Services\ProductService;

class WebProductController extends Controller
{
    public function __construct(
        protected ProductService $productService
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Product::class);

        $products = $this->productService->list([
            'search' => $request->query('search'),
            'category_id' => $request->query('category_id'),
            'brand_id' => $request->query('brand_id'),
            'is_active' => $request->query('is_active'),
            'sort_by' => $request->query('sort_by', 'created_at'),
            'sort_direction' => $request->query('sort_direction', 'desc'),
            'per_page' => $request->query('per_page', 20),
        ]);

        $orgId = session('current_organization_id');
        $categories = Category::forOrganization($orgId)->where('is_active', true)->orderBy('name')->get();
        $brands = Brand::forOrganization($orgId)->where('is_active', true)->orderBy('name')->get();

        return view('master-data.products.index', compact('products', 'categories', 'brands'));
    }

    public function create(): View
    {
        Gate::authorize('create', Product::class);

        $orgId = session('current_organization_id');
        $categories = Category::forOrganization($orgId)->where('is_active', true)->orderBy('name')->get();
        $brands = Brand::forOrganization($orgId)->where('is_active', true)->orderBy('name')->get();

        return view('master-data.products.create', compact('categories', 'brands'));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Product::class);

        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:50'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', 'integer'],
            'brand_id' => ['nullable', 'integer'],
        ]);

        try {
            if (empty($validated['category_id'])) {
                $defaultCat = Category::forOrganization(session('current_organization_id'))->first();
                $validated['category_id'] = $defaultCat?->id;
            }
            $this->productService->create($validated);
            return redirect()->route('products.index')->with('success', 'Product created successfully.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(int $id): View
    {
        $product = $this->productService->getById($id);
        Gate::authorize('view', $product);

        return view('master-data.products.show', compact('product'));
    }

    public function edit(int $id): View
    {
        $product = $this->productService->getById($id);
        Gate::authorize('update', $product);

        $orgId = session('current_organization_id');
        $categories = Category::forOrganization($orgId)->where('is_active', true)->orderBy('name')->get();
        $brands = Brand::forOrganization($orgId)->where('is_active', true)->orderBy('name')->get();

        return view('master-data.products.edit', compact('product', 'categories', 'brands'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $product = $this->productService->getById($id);
        Gate::authorize('update', $product);

        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:50'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', 'integer'],
            'brand_id' => ['nullable', 'integer'],
        ]);

        try {
            $this->productService->update($id, $validated);
            return redirect()->route('products.index')->with('success', 'Product updated successfully.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function activate(int $id): RedirectResponse
    {
        $product = $this->productService->getById($id);
        Gate::authorize('update', $product);

        $this->productService->setStatus($id, true);
        return back()->with('success', 'Product activated successfully.');
    }

    public function deactivate(int $id): RedirectResponse
    {
        $product = $this->productService->getById($id);
        Gate::authorize('update', $product);

        $this->productService->setStatus($id, false);
        return back()->with('success', 'Product deactivated successfully.');
    }
}
