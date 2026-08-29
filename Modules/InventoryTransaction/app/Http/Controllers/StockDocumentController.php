<?php

namespace Modules\InventoryTransaction\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\AuthenticationAudit\Traits\ApiResponse;
use Modules\InventoryTransaction\Exceptions\StockDocumentException;
use Modules\InventoryTransaction\Http\Requests\QueryStockDocumentRequest;
use Modules\InventoryTransaction\Http\Requests\StoreStockDocumentRequest;
use Modules\InventoryTransaction\Http\Requests\UpdateStockDocumentRequest;
use Modules\InventoryTransaction\Http\Resources\StockDocumentResource;
use Modules\InventoryTransaction\Models\StockDocument;
use Modules\InventoryTransaction\Services\CreateStockDocumentService;
use Modules\InventoryTransaction\Services\QueryStockDocumentService;
use Modules\InventoryTransaction\Services\UpdateStockDocumentService;

class StockDocumentController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected QueryStockDocumentService $queryService,
        protected CreateStockDocumentService $createService,
        protected UpdateStockDocumentService $updateService
    ) {}

    public function index(QueryStockDocumentRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', StockDocument::class);

        $paginated = $this->queryService->list($request->validated());

        return $this->paginatedResponse(
            StockDocumentResource::collection($paginated)
        );
    }

    public function store(StoreStockDocumentRequest $request): JsonResponse
    {
        Gate::authorize('create', StockDocument::class);

        try {
            $document = $this->createService->execute($request->validated());
            return $this->createdResponse(new StockDocumentResource($document), 'Stock document created successfully');
        } catch (StockDocumentException $e) {
            return $e->render();
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $document = $this->queryService->getById($id);
            Gate::authorize('view', $document);

            return $this->successResponse(new StockDocumentResource($document));
        } catch (StockDocumentException $e) {
            return $e->render();
        }
    }

    public function update(UpdateStockDocumentRequest $request, string $id): JsonResponse
    {
        try {
            $document = $this->queryService->getById($id);
            Gate::authorize('update', $document);

            $updated = $this->updateService->execute($id, $request->validated());
            return $this->successResponse(new StockDocumentResource($updated), 'Stock document updated successfully');
        } catch (StockDocumentException $e) {
            return $e->render();
        }
    }
}
