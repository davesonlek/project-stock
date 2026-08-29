<?php

namespace Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\AuthenticationAudit\Traits\ApiResponse;
use Modules\MasterData\Exceptions\MasterDataException;
use Modules\MasterData\Http\Requests\StoreCategoryRequest;
use Modules\MasterData\Http\Requests\UpdateCategoryRequest;
use Modules\MasterData\Http\Resources\CategoryResource;
use Modules\MasterData\Models\Category;
use Modules\MasterData\Services\CategoryService;

class CategoryController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected CategoryService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Category::class);

        $paginated = $this->service->list($request->all());

        return $this->paginatedResponse($paginated, CategoryResource::class);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        Gate::authorize('create', Category::class);

        $category = $this->service->create($request->validated());

        return $this->successResponse(new CategoryResource($category), 'Category created successfully', 201);
    }

    public function show(int $id): JsonResponse
    {
        Gate::authorize('view', Category::class);

        try {
            $category = $this->service->getById($id);
            return $this->successResponse(new CategoryResource($category));
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }

    public function update(UpdateCategoryRequest $request, int $id): JsonResponse
    {
        Gate::authorize('update', Category::class);

        try {
            $category = $this->service->update($id, $request->validated());
            return $this->successResponse(new CategoryResource($category), 'Category updated successfully');
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }

    public function activate(int $id): JsonResponse
    {
        Gate::authorize('activate', Category::class);

        try {
            $category = $this->service->setStatus($id, true);
            return $this->successResponse(new CategoryResource($category), 'Category activated successfully');
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }

    public function deactivate(int $id): JsonResponse
    {
        Gate::authorize('deactivate', Category::class);

        try {
            $category = $this->service->setStatus($id, false);
            return $this->successResponse(new CategoryResource($category), 'Category deactivated successfully');
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }

    public function destroy(int $id): JsonResponse
    {
        Gate::authorize('delete', Category::class);

        try {
            $this->service->delete($id);
            return $this->successResponse([], 'Category deleted successfully');
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }
}
