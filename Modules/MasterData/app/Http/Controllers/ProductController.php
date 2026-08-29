<?php

namespace Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\AuthenticationAudit\Traits\ApiResponse;
use Modules\MasterData\Exceptions\MasterDataException;
use Modules\MasterData\Http\Requests\StoreProductRequest;
use Modules\MasterData\Http\Requests\UpdateProductRequest;
use Modules\MasterData\Http\Resources\ProductResource;
use Modules\MasterData\Models\Product;
use Modules\MasterData\Services\ProductService;

class ProductController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ProductService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Product::class);

        $paginated = $this->service->list($request->all());

        return $this->paginatedResponse($paginated, ProductResource::class);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        Gate::authorize('create', Product::class);

        try {
            $product = $this->service->create($request->validated());
            return $this->successResponse(new ProductResource($product), 'Product created successfully', 201);
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }

    public function show(int $id): JsonResponse
    {
        Gate::authorize('view', Product::class);

        try {
            $product = $this->service->getById($id);
            return $this->successResponse(new ProductResource($product));
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }

    public function update(UpdateProductRequest $request, int $id): JsonResponse
    {
        Gate::authorize('update', Product::class);

        try {
            $product = $this->service->update($id, $request->validated());
            return $this->successResponse(new ProductResource($product), 'Product updated successfully');
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }

    public function activate(int $id): JsonResponse
    {
        Gate::authorize('activate', Product::class);

        try {
            $product = $this->service->setStatus($id, true);
            return $this->successResponse(new ProductResource($product), 'Product activated successfully');
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }

    public function deactivate(int $id): JsonResponse
    {
        Gate::authorize('deactivate', Product::class);

        try {
            $product = $this->service->setStatus($id, false);
            return $this->successResponse(new ProductResource($product), 'Product deactivated successfully');
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }

    public function destroy(int $id): JsonResponse
    {
        Gate::authorize('delete', Product::class);

        try {
            $this->service->delete($id);
            return $this->successResponse([], 'Product deleted successfully');
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }
}
