<?php

namespace Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\MasterData\Models\Category;
use Modules\MasterData\Services\CategoryService;

class WebCategoryController extends Controller
{
    public function __construct(
        protected CategoryService $categoryService
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Category::class);

        $categories = $this->categoryService->list([
            'search' => $request->query('search'),
            'is_active' => $request->query('is_active'),
            'sort_by' => $request->query('sort_by', 'created_at'),
            'sort_direction' => $request->query('sort_direction', 'desc'),
            'per_page' => $request->query('per_page', 20),
        ]);

        return view('master-data.categories.index', compact('categories'));
    }

    public function create(): View
    {
        Gate::authorize('create', Category::class);

        return view('master-data.categories.create');
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Category::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        try {
            $this->categoryService->create($validated);
            return redirect()->route('categories.index')->with('success', 'Category created successfully.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function edit(int $id): View
    {
        $category = $this->categoryService->getById($id);
        Gate::authorize('update', $category);

        return view('master-data.categories.edit', compact('category'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $category = $this->categoryService->getById($id);
        Gate::authorize('update', $category);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        try {
            $this->categoryService->update($id, $validated);
            return redirect()->route('categories.index')->with('success', 'Category updated successfully.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function activate(int $id): RedirectResponse
    {
        $category = $this->categoryService->getById($id);
        Gate::authorize('update', $category);

        $this->categoryService->setStatus($id, true);
        return back()->with('success', 'Category activated successfully.');
    }

    public function deactivate(int $id): RedirectResponse
    {
        $category = $this->categoryService->getById($id);
        Gate::authorize('update', $category);

        $this->categoryService->setStatus($id, false);
        return back()->with('success', 'Category deactivated successfully.');
    }
}
