<?php

namespace Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\MasterData\Models\Supplier;
use Modules\MasterData\Services\SupplierService;

class WebSupplierController extends Controller
{
    public function __construct(
        protected SupplierService $supplierService
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Supplier::class);

        $suppliers = $this->supplierService->list([
            'search' => $request->query('search'),
            'is_active' => $request->query('is_active'),
            'sort_by' => $request->query('sort_by', 'created_at'),
            'sort_direction' => $request->query('sort_direction', 'desc'),
            'per_page' => $request->query('per_page', 20),
        ]);

        return view('master-data.suppliers.index', compact('suppliers'));
    }

    public function create(): View
    {
        Gate::authorize('create', Supplier::class);

        return view('master-data.suppliers.create');
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Supplier::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'tax_id' => ['nullable', 'string', 'max:50'],
        ]);

        if (isset($validated['contact_name'])) {
            $validated['contact_person'] = $validated['contact_name'];
        }

        try {
            $this->supplierService->create($validated);
            return redirect()->route('suppliers.index')->with('success', 'Supplier created successfully.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function edit(int $id): View
    {
        $supplier = $this->supplierService->getById($id);
        Gate::authorize('update', $supplier);

        return view('master-data.suppliers.edit', compact('supplier'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $supplier = $this->supplierService->getById($id);
        Gate::authorize('update', $supplier);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'tax_id' => ['nullable', 'string', 'max:50'],
        ]);

        if (isset($validated['contact_name'])) {
            $validated['contact_person'] = $validated['contact_name'];
        }

        try {
            $this->supplierService->update($id, $validated);
            return redirect()->route('suppliers.index')->with('success', 'Supplier updated successfully.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function activate(int $id): RedirectResponse
    {
        $supplier = $this->supplierService->getById($id);
        Gate::authorize('update', $supplier);

        $this->supplierService->setStatus($id, true);
        return back()->with('success', 'Supplier activated successfully.');
    }

    public function deactivate(int $id): RedirectResponse
    {
        $supplier = $this->supplierService->getById($id);
        Gate::authorize('update', $supplier);

        $this->supplierService->setStatus($id, false);
        return back()->with('success', 'Supplier deactivated successfully.');
    }
}
