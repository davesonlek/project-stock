<?php

namespace Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\AuthenticationAudit\Traits\ApiResponse;
use Modules\MasterData\Exceptions\MasterDataException;
use Modules\MasterData\Http\Requests\StoreSupplierRequest;
use Modules\MasterData\Http\Requests\UpdateSupplierRequest;
use Modules\MasterData\Http\Resources\SupplierResource;
use Modules\MasterData\Models\Supplier;
use Modules\MasterData\Services\SupplierService;

class SupplierController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected SupplierService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Supplier::class);

        $paginated = $this->service->list($request->all());

        return $this->paginatedResponse($paginated, SupplierResource::class);
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        Gate::authorize('create', Supplier::class);

        $supplier = $this->service->create($request->validated());

        return $this->successResponse(new SupplierResource($supplier), 'Supplier created successfully', 201);
    }

    public function show(int $id): JsonResponse
    {
        Gate::authorize('view', Supplier::class);

        try {
            $supplier = $this->service->getById($id);
            return $this->successResponse(new SupplierResource($supplier));
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }

    public function update(UpdateSupplierRequest $request, int $id): JsonResponse
    {
        Gate::authorize('update', Supplier::class);

        try {
            $supplier = $this->service->update($id, $request->validated());
            return $this->successResponse(new SupplierResource($supplier), 'Supplier updated successfully');
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }

    public function activate(int $id): JsonResponse
    {
        Gate::authorize('activate', Supplier::class);

        try {
            $supplier = $this->service->setStatus($id, true);
            return $this->successResponse(new SupplierResource($supplier), 'Supplier activated successfully');
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }

    public function deactivate(int $id): JsonResponse
    {
        Gate::authorize('deactivate', Supplier::class);

        try {
            $supplier = $this->service->setStatus($id, false);
            return $this->successResponse(new SupplierResource($supplier), 'Supplier deactivated successfully');
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }

    public function destroy(int $id): JsonResponse
    {
        Gate::authorize('delete', Supplier::class);

        try {
            $this->service->delete($id);
            return $this->successResponse([], 'Supplier deleted successfully');
        } catch (MasterDataException $e) {
            return $e->render();
        }
    }
}
