<?php

namespace Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\AuthenticationAudit\Traits\ApiResponse;
use Modules\MasterData\Exceptions\MasterDataException;
use Modules\MasterData\Http\Requests\StoreBrandRequest;
use Modules\MasterData\Http\Requests\UpdateBrandRequest;
use Modules\MasterData\Http\Resources\BrandResource;
use Modules\MasterData\Models\Brand;
use Modules\MasterData\Services\BrandService;

class BrandController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected BrandService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Brand::class);

        $paginated = $this->service->list($request->all());

        return $this->paginatedResponse($paginated, BrandResource::class);
    }

    public function store(StoreBrandRequest $request): JsonResponse
    {
        Gate::authorize('create', Brand::class);

        $brand = $this->service->create($request->validated());

        return $this->successResponse(new BrandResource($brand), 'Brand created successfully', 201);
    }

    public function show(int $id): JsonResponse
    {
        Gate::authorize('view', Brand::class);

        try {
            $brand = $this->service->getById($id);
            return $this->successResponse(new BrandResource($brand));
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }

    public function update(UpdateBrandRequest $request, int $id): JsonResponse
    {
        Gate::authorize('update', Brand::class);

        try {
            $brand = $this->service->update($id, $request->validated());
            return $this->successResponse(new BrandResource($brand), 'Brand updated successfully');
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }

    public function activate(int $id): JsonResponse
    {
        Gate::authorize('activate', Brand::class);

        try {
            $brand = $this->service->setStatus($id, true);
            return $this->successResponse(new BrandResource($brand), 'Brand activated successfully');
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }

    public function deactivate(int $id): JsonResponse
    {
        Gate::authorize('deactivate', Brand::class);

        try {
            $brand = $this->service->setStatus($id, false);
            return $this->successResponse(new BrandResource($brand), 'Brand deactivated successfully');
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }

    public function destroy(int $id): JsonResponse
    {
        Gate::authorize('delete', Brand::class);

        try {
            $this->service->delete($id);
            return $this->successResponse([], 'Brand deleted successfully');
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }
}
