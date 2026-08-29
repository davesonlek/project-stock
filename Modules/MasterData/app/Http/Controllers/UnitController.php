<?php

namespace Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\AuthenticationAudit\Traits\ApiResponse;
use Modules\MasterData\Exceptions\MasterDataException;
use Modules\MasterData\Http\Requests\StoreUnitRequest;
use Modules\MasterData\Http\Requests\UpdateUnitRequest;
use Modules\MasterData\Http\Resources\UnitResource;
use Modules\MasterData\Models\Unit;
use Modules\MasterData\Services\UnitService;

class UnitController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected UnitService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Unit::class);

        $paginated = $this->service->list($request->all());

        return $this->paginatedResponse($paginated, UnitResource::class);
    }

    public function store(StoreUnitRequest $request): JsonResponse
    {
        Gate::authorize('create', Unit::class);

        $unit = $this->service->create($request->validated());

        return $this->successResponse(new UnitResource($unit), 'Unit created successfully', 201);
    }

    public function show(int $id): JsonResponse
    {
        Gate::authorize('view', Unit::class);

        try {
            $unit = $this->service->getById($id);
            return $this->successResponse(new UnitResource($unit));
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }

    public function update(UpdateUnitRequest $request, int $id): JsonResponse
    {
        Gate::authorize('update', Unit::class);

        try {
            $unit = $this->service->update($id, $request->validated());
            return $this->successResponse(new UnitResource($unit), 'Unit updated successfully');
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }

    public function activate(int $id): JsonResponse
    {
        Gate::authorize('activate', Unit::class);

        try {
            $unit = $this->service->setStatus($id, true);
            return $this->successResponse(new UnitResource($unit), 'Unit activated successfully');
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }

    public function deactivate(int $id): JsonResponse
    {
        Gate::authorize('deactivate', Unit::class);

        try {
            $unit = $this->service->setStatus($id, false);
            return $this->successResponse(new UnitResource($unit), 'Unit deactivated successfully');
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }

    public function destroy(int $id): JsonResponse
    {
        Gate::authorize('delete', Unit::class);

        try {
            $this->service->delete($id);
            return $this->successResponse([], 'Unit deleted successfully');
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }
}
