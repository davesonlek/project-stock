<?php

namespace Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Product;
use Modules\MasterData\Models\Unit;
use Modules\MasterData\Services\GoodsService;

class WebGoodsController extends Controller
{
    public function __construct(
        protected GoodsService $goodsService
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Goods::class);

        $goodsList = $this->goodsService->list([
            'search' => $request->query('search'),
            'product_id' => $request->query('product_id'),
            'unit_id' => $request->query('unit_id'),
            'is_active' => $request->query('is_active'),
            'is_lot_tracked' => $request->query('is_lot_tracked'),
            'is_serial_tracked' => $request->query('is_serial_tracked'),
            'sort_by' => $request->query('sort_by', 'created_at'),
            'sort_direction' => $request->query('sort_direction', 'desc'),
            'per_page' => $request->query('per_page', 20),
        ]);

        $orgId = session('current_organization_id');
        $products = Product::forOrganization($orgId)->where('is_active', true)->orderBy('name')->get();
        $units = Unit::forOrganization($orgId)->where('is_active', true)->orderBy('name')->get();

        return view('master-data.goods.index', compact('goodsList', 'products', 'units'));
    }

    public function create(): View
    {
        Gate::authorize('create', Goods::class);

        $orgId = session('current_organization_id');
        $products = Product::forOrganization($orgId)->where('is_active', true)->orderBy('name')->get();
        $units = Unit::forOrganization($orgId)->where('is_active', true)->orderBy('name')->get();

        return view('master-data.goods.create', compact('products', 'units'));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Goods::class);

        $validated = $request->validate([
            'product_id' => ['required', 'integer'],
            'unit_id' => ['required', 'integer'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'pack_size' => ['required', 'integer', 'min:1'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'sell_price' => ['nullable', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'is_lot_tracked' => ['nullable'],
            'is_serial_tracked' => ['nullable'],
        ]);

        $validated['cost'] = $validated['cost'] ?? $validated['cost_price'] ?? 0;
        $validated['sell_price'] = $validated['sell_price'] ?? $validated['selling_price'] ?? 0;
        $validated['is_lot_tracked'] = $request->boolean('is_lot_tracked');
        $validated['is_serial_tracked'] = $request->boolean('is_serial_tracked');

        try {
            $this->goodsService->create($validated);
            return redirect()->route('goods.index')->with('success', 'Goods created successfully.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function edit(int $id): View
    {
        $goods = $this->goodsService->getById($id);
        Gate::authorize('update', $goods);

        $orgId = session('current_organization_id');
        $products = Product::forOrganization($orgId)->where('is_active', true)->orderBy('name')->get();
        $units = Unit::forOrganization($orgId)->where('is_active', true)->orderBy('name')->get();

        return view('master-data.goods.edit', compact('goods', 'products', 'units'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $goods = $this->goodsService->getById($id);
        Gate::authorize('update', $goods);

        $validated = $request->validate([
            'barcode' => ['nullable', 'string', 'max:100'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'sell_price' => ['nullable', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        if (isset($validated['cost_price'])) {
            $validated['cost'] = $validated['cost_price'];
        }
        if (isset($validated['selling_price'])) {
            $validated['sell_price'] = $validated['selling_price'];
        }

        try {
            $this->goodsService->update($id, $validated);
            return redirect()->route('goods.index')->with('success', 'Goods updated successfully.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function activate(int $id): RedirectResponse
    {
        $goods = $this->goodsService->getById($id);
        Gate::authorize('update', $goods);

        $this->goodsService->setStatus($id, true);
        return back()->with('success', 'Goods activated successfully.');
    }

    public function deactivate(int $id): RedirectResponse
    {
        $goods = $this->goodsService->getById($id);
        Gate::authorize('update', $goods);

        $this->goodsService->setStatus($id, false);
        return back()->with('success', 'Goods deactivated successfully.');
    }
}
