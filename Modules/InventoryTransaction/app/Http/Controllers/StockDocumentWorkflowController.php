<?php

namespace Modules\InventoryTransaction\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Modules\AuthenticationAudit\Traits\ApiResponse;
use Modules\InventoryTransaction\Exceptions\StockDocumentException;
use Modules\InventoryTransaction\Http\Requests\CancelStockDocumentRequest;
use Modules\InventoryTransaction\Http\Requests\PostStockDocumentRequest;
use Modules\InventoryTransaction\Http\Requests\ReverseStockDocumentRequest;
use Modules\InventoryTransaction\Http\Resources\StockDocumentResource;
use Modules\InventoryTransaction\Services\ApproveStockDocumentService;
use Modules\InventoryTransaction\Services\CancelStockDocumentService;
use Modules\InventoryTransaction\Services\PostStockDocumentService;
use Modules\InventoryTransaction\Services\QueryStockDocumentService;
use Modules\InventoryTransaction\Services\ReverseStockDocumentService;
use Modules\InventoryTransaction\Services\SubmitStockDocumentService;

class StockDocumentWorkflowController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected QueryStockDocumentService $queryService,
        protected SubmitStockDocumentService $submitService,
        protected ApproveStockDocumentService $approveService,
        protected CancelStockDocumentService $cancelService,
        protected PostStockDocumentService $postService,
        protected ReverseStockDocumentService $reverseService
    ) {}

    public function submit(string $id): JsonResponse
    {
        try {
            $document = $this->queryService->getById($id);
            Gate::authorize('submit', $document);

            $submitted = $this->submitService->execute($id);
            return $this->successResponse(new StockDocumentResource($submitted), 'Stock document submitted for approval');
        } catch (StockDocumentException $e) {
            return $e->render();
        }
    }

    public function approve(string $id): JsonResponse
    {
        try {
            $document = $this->queryService->getById($id);
            Gate::authorize('approve', $document);

            $approved = $this->approveService->execute($id);
            return $this->successResponse(new StockDocumentResource($approved), 'Stock document approved successfully');
        } catch (StockDocumentException $e) {
            return $e->render();
        }
    }

    public function cancel(CancelStockDocumentRequest $request, string $id): JsonResponse
    {
        try {
            $document = $this->queryService->getById($id);
            Gate::authorize('cancel', $document);

            $cancelled = $this->cancelService->execute($id, $request->input('reason'));
            return $this->successResponse(new StockDocumentResource($cancelled), 'Stock document cancelled successfully');
        } catch (StockDocumentException $e) {
            return $e->render();
        }
    }

    public function post(PostStockDocumentRequest $request, string $document): JsonResponse
    {
        try {
            $idempotencyKey = $request->header('Idempotency-Key');
            if (empty($idempotencyKey) || !is_string($idempotencyKey) || strlen($idempotencyKey) > 100) {
                return response()->json([
                    'success' => false,
                    'code' => 'IDEMPOTENCY_KEY_REQUIRED',
                    'message' => 'Idempotency-Key header is required for stock posting.',
                    'errors' => (object) [],
                ], 422);
            }

            $doc = $this->queryService->getById($document);
            Gate::authorize('post', $doc);

            $result = $this->postService->execute($document, (string) $idempotencyKey, $request->all());

            return $this->successResponse(
                new StockDocumentResource($result['document']),
                $result['is_replay'] ? 'Stock document already posted (idempotent response)' : 'Stock document posted successfully'
            );
        } catch (StockDocumentException $e) {
            return $e->render();
        }
    }

    public function reverse(ReverseStockDocumentRequest $request, string $document): JsonResponse
    {
        try {
            $idempotencyKey = (string) $request->header('Idempotency-Key');

            $doc = $this->queryService->getById($document);
            Gate::authorize('reverse', $doc);

            $result = $this->reverseService->execute($document, $idempotencyKey, $request->validated());

            return response()->json($result, 200);
        } catch (StockDocumentException $e) {
            return $e->render();
        }
    }
}
