<?php

namespace Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\AuthenticationAudit\Traits\ApiResponse;
use Modules\MasterData\Exceptions\MasterDataException;
use Modules\MasterData\Http\Requests\StoreWarehouseRequest;
use Modules\MasterData\Http\Requests\UpdateWarehouseRequest;
use Modules\MasterData\Http\Resources\WarehouseLocationResource;
use Modules\MasterData\Http\Resources\WarehouseResource;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Services\WarehouseLocationService;
use Modules\MasterData\Services\WarehouseService;

class WarehouseController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected WarehouseService $service,
        protected WarehouseLocationService $locationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Warehouse::class);

        $paginated = $this->service->list($request->all());

        return $this->paginatedResponse($paginated, WarehouseResource::class);
    }

    public function store(StoreWarehouseRequest $request): JsonResponse
    {
        Gate::authorize('create', Warehouse::class);

        try {
            $warehouse = $this->service->create($request->validated());
            return $this->successResponse(new WarehouseResource($warehouse), 'Warehouse created successfully', 201);
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }

    public function show(int $id): JsonResponse
    {
        Gate::authorize('view', Warehouse::class);

        try {
            $warehouse = $this->service->getById($id);
            return $this->successResponse(new WarehouseResource($warehouse));
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }

    public function update(UpdateWarehouseRequest $request, int $id): JsonResponse
    {
        Gate::authorize('update', Warehouse::class);

        try {
            $warehouse = $this->service->update($id, $request->validated());
            return $this->successResponse(new WarehouseResource($warehouse), 'Warehouse updated successfully');
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }

    public function activate(int $id): JsonResponse
    {
        Gate::authorize('activate', Warehouse::class);

        try {
            $warehouse = $this->service->setStatus($id, true);
            return $this->successResponse(new WarehouseResource($warehouse), 'Warehouse activated successfully');
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }

    public function deactivate(int $id): JsonResponse
    {
        Gate::authorize('deactivate', Warehouse::class);

        try {
            $warehouse = $this->service->setStatus($id, false);
            return $this->successResponse(new WarehouseResource($warehouse), 'Warehouse deactivated successfully');
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }

    public function destroy(int $id): JsonResponse
    {
        Gate::authorize('delete', Warehouse::class);

        try {
            $this->service->delete($id);
            return $this->successResponse([], 'Warehouse deleted successfully');
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }

    /**
     * Nested locations for a warehouse.
     */
    public function locations(Request $request, int $warehouseId): JsonResponse
    {
        Gate::authorize('viewAny', Warehouse::class);

        // Verify warehouse exists in current organization
        try {
            $this->service->getById($warehouseId);
        } catch (MasterDataException $e) {
            return $e->render();
        }

        $filters = array_merge($request->all(), ['warehouse_id' => $warehouseId]);
        $paginated = $this->locationService->list($filters);

        return $this->paginatedResponse($paginated, WarehouseLocationResource::class);
    }
}
