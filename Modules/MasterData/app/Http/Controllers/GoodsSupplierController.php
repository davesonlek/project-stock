<?php

namespace Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\AuthenticationAudit\Traits\ApiResponse;
use Modules\MasterData\Exceptions\MasterDataException;
use Modules\MasterData\Http\Requests\StoreGoodsSupplierRequest;
use Modules\MasterData\Http\Requests\UpdateGoodsSupplierRequest;
use Modules\MasterData\Http\Resources\GoodsSupplierResource;
use Modules\MasterData\Models\GoodsSupplier;
use Modules\MasterData\Services\GoodsSupplierService;

class GoodsSupplierController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected GoodsSupplierService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', GoodsSupplier::class);

        $paginated = $this->service->list($request->all());

        return $this->paginatedResponse($paginated, GoodsSupplierResource::class);
    }

    public function store(StoreGoodsSupplierRequest $request): JsonResponse
    {
        Gate::authorize('create', GoodsSupplier::class);

        try {
            $goodsSupplier = $this->service->create($request->validated());
            return $this->successResponse(new GoodsSupplierResource($goodsSupplier), 'Goods supplier relation created successfully', 201);
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }

    public function show(int $id): JsonResponse
    {
        Gate::authorize('view', GoodsSupplier::class);

        try {
            $goodsSupplier = $this->service->getById($id);
            return $this->successResponse(new GoodsSupplierResource($goodsSupplier));
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }

    public function update(UpdateGoodsSupplierRequest $request, int $id): JsonResponse
    {
        Gate::authorize('update', GoodsSupplier::class);

        try {
            $goodsSupplier = $this->service->update($id, $request->validated());
            return $this->successResponse(new GoodsSupplierResource($goodsSupplier), 'Goods supplier relation updated successfully');
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }

    public function destroy(int $id): JsonResponse
    {
        Gate::authorize('delete', GoodsSupplier::class);

        try {
            $this->service->delete($id);
            return $this->successResponse([], 'Goods supplier relation deleted successfully');
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }
}
