<?php

namespace Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\MasterData\Models\Brand;
use Modules\MasterData\Services\BrandService;

class WebBrandController extends Controller
{
    public function __construct(
        protected BrandService $brandService
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Brand::class);

        $brands = $this->brandService->list([
            'search' => $request->query('search'),
            'is_active' => $request->query('is_active'),
            'sort_by' => $request->query('sort_by', 'created_at'),
            'sort_direction' => $request->query('sort_direction', 'desc'),
            'per_page' => $request->query('per_page', 20),
        ]);

        return view('master-data.brands.index', compact('brands'));
    }

    public function create(): View
    {
        Gate::authorize('create', Brand::class);

        return view('master-data.brands.create');
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Brand::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        try {
            $this->brandService->create($validated);
            return redirect()->route('brands.index')->with('success', 'Brand created successfully.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function edit(int $id): View
    {
        $brand = $this->brandService->getById($id);
        Gate::authorize('update', $brand);

        return view('master-data.brands.edit', compact('brand'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $brand = $this->brandService->getById($id);
        Gate::authorize('update', $brand);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        try {
            $this->brandService->update($id, $validated);
            return redirect()->route('brands.index')->with('success', 'Brand updated successfully.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function activate(int $id): RedirectResponse
    {
        $brand = $this->brandService->getById($id);
        Gate::authorize('update', $brand);

        $this->brandService->setStatus($id, true);
        return back()->with('success', 'Brand activated successfully.');
    }

    public function deactivate(int $id): RedirectResponse
    {
        $brand = $this->brandService->getById($id);
        Gate::authorize('update', $brand);

        $this->brandService->setStatus($id, false);
        return back()->with('success', 'Brand deactivated successfully.');
    }
}
