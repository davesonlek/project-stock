<?php

namespace Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\MasterData\Models\Unit;
use Modules\MasterData\Services\UnitService;

class WebUnitController extends Controller
{
    public function __construct(
        protected UnitService $unitService
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Unit::class);

        $units = $this->unitService->list([
            'search' => $request->query('search'),
            'is_active' => $request->query('is_active'),
            'sort_by' => $request->query('sort_by', 'created_at'),
            'sort_direction' => $request->query('sort_direction', 'desc'),
            'per_page' => $request->query('per_page', 20),
        ]);

        return view('master-data.units.index', compact('units'));
    }

    public function create(): View
    {
        Gate::authorize('create', Unit::class);

        return view('master-data.units.create');
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Unit::class);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        try {
            $this->unitService->create($validated);
            return redirect()->route('units.index')->with('success', 'Unit created successfully.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function edit(int $id): View
    {
        $unit = $this->unitService->getById($id);
        Gate::authorize('update', $unit);

        return view('master-data.units.edit', compact('unit'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $unit = $this->unitService->getById($id);
        Gate::authorize('update', $unit);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        try {
            $this->unitService->update($id, $validated);
            return redirect()->route('units.index')->with('success', 'Unit updated successfully.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function activate(int $id): RedirectResponse
    {
        $unit = $this->unitService->getById($id);
        Gate::authorize('update', $unit);

        $this->unitService->setStatus($id, true);
        return back()->with('success', 'Unit activated successfully.');
    }

    public function deactivate(int $id): RedirectResponse
    {
        $unit = $this->unitService->getById($id);
        Gate::authorize('update', $unit);

        $this->unitService->setStatus($id, false);
        return back()->with('success', 'Unit deactivated successfully.');
    }
}
