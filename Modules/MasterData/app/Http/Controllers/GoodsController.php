<?php

namespace Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\AuthenticationAudit\Traits\ApiResponse;
use Modules\MasterData\Exceptions\MasterDataException;
use Modules\MasterData\Http\Requests\StoreGoodsRequest;
use Modules\MasterData\Http\Requests\UpdateGoodsRequest;
use Modules\MasterData\Http\Resources\GoodsResource;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Services\GoodsService;

class GoodsController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected GoodsService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Goods::class);

        $paginated = $this->service->list($request->all());

        return $this->paginatedResponse($paginated, GoodsResource::class);
    }

    public function store(StoreGoodsRequest $request): JsonResponse
    {
        Gate::authorize('create', Goods::class);

        try {
            $goods = $this->service->create($request->validated());
            return $this->successResponse(new GoodsResource($goods), 'Goods created successfully', 201);
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }

    public function show(int $id): JsonResponse
    {
        Gate::authorize('view', Goods::class);

        try {
            $goods = $this->service->getById($id);
            return $this->successResponse(new GoodsResource($goods));
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }

    public function update(UpdateGoodsRequest $request, int $id): JsonResponse
    {
        Gate::authorize('update', Goods::class);

        try {
            $goods = $this->service->update($id, $request->validated());
            return $this->successResponse(new GoodsResource($goods), 'Goods updated successfully');
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }

    public function activate(int $id): JsonResponse
    {
        Gate::authorize('activate', Goods::class);

        try {
            $goods = $this->service->setStatus($id, true);
            return $this->successResponse(new GoodsResource($goods), 'Goods activated successfully');
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }

    public function deactivate(int $id): JsonResponse
    {
        Gate::authorize('deactivate', Goods::class);

        try {
            $goods = $this->service->setStatus($id, false);
            return $this->successResponse(new GoodsResource($goods), 'Goods deactivated successfully');
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }

    public function destroy(int $id): JsonResponse
    {
        Gate::authorize('delete', Goods::class);

        try {
            $this->service->delete($id);
            return $this->successResponse([], 'Goods deleted successfully');
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }
}
