<?php

namespace Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\AuthenticationAudit\Models\User;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Services\WarehouseService;

class WebWarehouseController extends Controller
{
    public function __construct(
        protected WarehouseService $warehouseService
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Warehouse::class);

        $warehouses = $this->warehouseService->list([
            'search' => $request->query('search'),
            'is_active' => $request->query('is_active'),
            'sort_by' => $request->query('sort_by', 'created_at'),
            'sort_direction' => $request->query('sort_direction', 'desc'),
            'per_page' => $request->query('per_page', 20),
        ]);

        return view('master-data.warehouses.index', compact('warehouses'));
    }

    public function create(): View
    {
        Gate::authorize('create', Warehouse::class);

        $orgId = session('current_organization_id');
        $users = User::whereHas('userOrganizations', function ($q) use ($orgId) {
            $q->where('organization_id', $orgId);
        })->where('is_active', true)->orderBy('username')->get();

        return view('master-data.warehouses.create', compact('users'));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Warehouse::class);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'manager_id' => ['nullable', 'uuid'],
        ]);

        try {
            $this->warehouseService->create($validated);
            return redirect()->route('warehouses.index')->with('success', 'Warehouse created successfully.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(int $id): View
    {
        $warehouse = $this->warehouseService->getById($id);
        Gate::authorize('view', $warehouse);

        $warehouse->load(['locations' => function ($q) {
            $q->orderBy('code');
        }, 'manager']);

        return view('master-data.warehouses.show', compact('warehouse'));
    }

    public function edit(int $id): View
    {
        $warehouse = $this->warehouseService->getById($id);
        Gate::authorize('update', $warehouse);

        $orgId = session('current_organization_id');
        $users = User::whereHas('userOrganizations', function ($q) use ($orgId) {
            $q->where('organization_id', $orgId);
        })->where('is_active', true)->orderBy('username')->get();

        return view('master-data.warehouses.edit', compact('warehouse', 'users'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $warehouse = $this->warehouseService->getById($id);
        Gate::authorize('update', $warehouse);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'manager_id' => ['nullable', 'integer'],
        ]);

        try {
            $this->warehouseService->update($id, $validated);
            return redirect()->route('warehouses.index')->with('success', 'Warehouse updated successfully.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function activate(int $id): RedirectResponse
    {
        $warehouse = $this->warehouseService->getById($id);
        Gate::authorize('update', $warehouse);

        $this->warehouseService->setStatus($id, true);
        return back()->with('success', 'Warehouse activated successfully.');
    }

    public function deactivate(int $id): RedirectResponse
    {
        $warehouse = $this->warehouseService->getById($id);
        Gate::authorize('update', $warehouse);

        $this->warehouseService->setStatus($id, false);
        return back()->with('success', 'Warehouse deactivated successfully.');
    }
}
