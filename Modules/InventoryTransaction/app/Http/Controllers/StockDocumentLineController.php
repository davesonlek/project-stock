<?php

namespace Modules\InventoryTransaction\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Modules\AuthenticationAudit\Traits\ApiResponse;
use Modules\InventoryTransaction\Exceptions\StockDocumentException;
use Modules\InventoryTransaction\Http\Requests\StoreStockDocumentLineRequest;
use Modules\InventoryTransaction\Http\Requests\UpdateStockDocumentLineRequest;
use Modules\InventoryTransaction\Http\Resources\StockDocumentLineResource;
use Modules\InventoryTransaction\Services\CreateStockDocumentLineService;
use Modules\InventoryTransaction\Services\DeleteStockDocumentLineService;
use Modules\InventoryTransaction\Services\QueryStockDocumentService;
use Modules\InventoryTransaction\Services\UpdateStockDocumentLineService;

class StockDocumentLineController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected QueryStockDocumentService $queryService,
        protected CreateStockDocumentLineService $createLineService,
        protected UpdateStockDocumentLineService $updateLineService,
        protected DeleteStockDocumentLineService $deleteLineService
    ) {}

    public function index(string $documentId): JsonResponse
    {
        try {
            $document = $this->queryService->getById($documentId);
            Gate::authorize('view', $document);

            return $this->successResponse(
                StockDocumentLineResource::collection($document->lines)
            );
        } catch (StockDocumentException $e) {
            return $e->render();
        }
    }

    public function store(StoreStockDocumentLineRequest $request, string $documentId): JsonResponse
    {
        try {
            $document = $this->queryService->getById($documentId);
            Gate::authorize('addLine', $document);

            $line = $this->createLineService->execute($documentId, $request->validated());
            return $this->createdResponse(new StockDocumentLineResource($line), 'Stock document line added successfully');
        } catch (StockDocumentException $e) {
            return $e->render();
        }
    }

    public function update(UpdateStockDocumentLineRequest $request, string $documentId, int $lineId): JsonResponse
    {
        try {
            $document = $this->queryService->getById($documentId);
            Gate::authorize('updateLine', $document);

            $line = $this->updateLineService->execute($documentId, $lineId, $request->validated());
            return $this->successResponse(new StockDocumentLineResource($line), 'Stock document line updated successfully');
        } catch (StockDocumentException $e) {
            return $e->render();
        }
    }

    public function destroy(string $documentId, int $lineId): JsonResponse
    {
        try {
            $document = $this->queryService->getById($documentId);
            Gate::authorize('deleteLine', $document);

            $this->deleteLineService->execute($documentId, $lineId);
            return $this->successResponse([], 'Stock document line deleted successfully');
        } catch (StockDocumentException $e) {
            return $e->render();
        }
    }
}
