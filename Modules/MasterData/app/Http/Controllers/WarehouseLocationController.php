<?php

namespace Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\AuthenticationAudit\Traits\ApiResponse;
use Modules\MasterData\Exceptions\MasterDataException;
use Modules\MasterData\Http\Requests\StoreWarehouseLocationRequest;
use Modules\MasterData\Http\Requests\UpdateWarehouseLocationRequest;
use Modules\MasterData\Http\Resources\WarehouseLocationResource;
use Modules\MasterData\Models\WarehouseLocation;
use Modules\MasterData\Services\WarehouseLocationService;

class WarehouseLocationController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected WarehouseLocationService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', WarehouseLocation::class);

        $paginated = $this->service->list($request->all());

        return $this->paginatedResponse($paginated, WarehouseLocationResource::class);
    }

    public function store(StoreWarehouseLocationRequest $request): JsonResponse
    {
        Gate::authorize('create', WarehouseLocation::class);

        try {
            $location = $this->service->create($request->validated());
            return $this->successResponse(new WarehouseLocationResource($location), 'Warehouse location created successfully', 201);
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }

    public function show(int $id): JsonResponse
    {
        Gate::authorize('view', WarehouseLocation::class);

        try {
            $location = $this->service->getById($id);
            return $this->successResponse(new WarehouseLocationResource($location));
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }

    public function update(UpdateWarehouseLocationRequest $request, int $id): JsonResponse
    {
        Gate::authorize('update', WarehouseLocation::class);

        try {
            $location = $this->service->update($id, $request->validated());
            return $this->successResponse(new WarehouseLocationResource($location), 'Warehouse location updated successfully');
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }

    public function activate(int $id): JsonResponse
    {
        Gate::authorize('activate', WarehouseLocation::class);

        try {
            $location = $this->service->setStatus($id, true);
            return $this->successResponse(new WarehouseLocationResource($location), 'Warehouse location activated successfully');
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }

    public function deactivate(int $id): JsonResponse
    {
        Gate::authorize('deactivate', WarehouseLocation::class);

        try {
            $location = $this->service->setStatus($id, false);
            return $this->successResponse(new WarehouseLocationResource($location), 'Warehouse location deactivated successfully');
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }

    public function destroy(int $id): JsonResponse
    {
        Gate::authorize('delete', WarehouseLocation::class);

        try {
            $this->service->delete($id);
            return $this->successResponse([], 'Warehouse location deleted successfully');
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }
}
