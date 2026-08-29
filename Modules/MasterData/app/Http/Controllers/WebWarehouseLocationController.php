<?php

namespace Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;
use Modules\MasterData\Services\WarehouseLocationService;

class WebWarehouseLocationController extends Controller
{
    public function __construct(
        protected WarehouseLocationService $locationService
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', WarehouseLocation::class);

        $locations = $this->locationService->list([
            'search' => $request->query('search'),
            'warehouse_id' => $request->query('warehouse_id'),
            'is_active' => $request->query('is_active'),
            'sort_by' => $request->query('sort_by', 'created_at'),
            'sort_direction' => $request->query('sort_direction', 'desc'),
            'per_page' => $request->query('per_page', 20),
        ]);

        $orgId = session('current_organization_id');
        $warehouses = Warehouse::forOrganization($orgId)->where('is_active', true)->orderBy('name')->get();

        return view('master-data.locations.index', compact('locations', 'warehouses'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', WarehouseLocation::class);

        $orgId = session('current_organization_id');
        $warehouses = Warehouse::forOrganization($orgId)->where('is_active', true)->orderBy('name')->get();
        $selectedWarehouseId = $request->query('warehouse_id');

        return view('master-data.locations.create', compact('warehouses', 'selectedWarehouseId'));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', WarehouseLocation::class);

        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer'],
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'zone' => ['nullable', 'string', 'max:50'],
            'aisle' => ['nullable', 'string', 'max:50'],
            'rack' => ['nullable', 'string', 'max:50'],
            'shelf' => ['nullable', 'string', 'max:50'],
            'bin' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $this->locationService->create($validated);
            return redirect()->route('warehouse-locations.index')->with('success', 'Location created successfully.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function edit(int $id): View
    {
        $location = $this->locationService->getById($id);
        Gate::authorize('update', $location);

        $orgId = session('current_organization_id');
        $warehouses = Warehouse::forOrganization($orgId)->where('is_active', true)->orderBy('name')->get();

        return view('master-data.locations.edit', compact('location', 'warehouses'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $location = $this->locationService->getById($id);
        Gate::authorize('update', $location);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['nullable', 'string', 'max:255'],
            'zone' => ['nullable', 'string', 'max:50'],
            'aisle' => ['nullable', 'string', 'max:50'],
            'rack' => ['nullable', 'string', 'max:50'],
            'shelf' => ['nullable', 'string', 'max:50'],
            'bin' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $this->locationService->update($id, $validated);
            return redirect()->route('warehouse-locations.index')->with('success', 'Location updated successfully.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function activate(int $id): RedirectResponse
    {
        $location = $this->locationService->getById($id);
        Gate::authorize('update', $location);

        $this->locationService->setStatus($id, true);
        return back()->with('success', 'Location activated successfully.');
    }

    public function deactivate(int $id): RedirectResponse
    {
        $location = $this->locationService->getById($id);
        Gate::authorize('update', $location);

        $this->locationService->setStatus($id, false);
        return back()->with('success', 'Location deactivated successfully.');
    }
}
