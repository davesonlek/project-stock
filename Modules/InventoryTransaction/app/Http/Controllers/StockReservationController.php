<?php

namespace Modules\InventoryTransaction\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\AuthenticationAudit\Traits\ApiResponse;
use Modules\InventoryTransaction\Exceptions\StockDocumentException;
use Modules\InventoryTransaction\Http\Requests\ReserveStockRequest;
use Modules\InventoryTransaction\Http\Resources\StockReservationResource;
use Modules\InventoryTransaction\Models\StockReservation;
use Modules\InventoryTransaction\Services\QueryStockReservationService;
use Modules\InventoryTransaction\Services\ReleaseStockReservationService;
use Modules\InventoryTransaction\Services\ReserveStockService;

class StockReservationController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ReserveStockService $reserveService,
        protected ReleaseStockReservationService $releaseService,
        protected QueryStockReservationService $queryService
    ) {}

    public function reserve(ReserveStockRequest $request, string $documentId, int $lineId): JsonResponse
    {
        Gate::authorize('reserve', StockReservation::class);

        try {
            $reservation = $this->reserveService->execute(
                documentId: $documentId,
                lineId: $lineId,
                expiresAt: $request->validated('expires_at')
            );

            return $this->successResponse(
                new StockReservationResource($reservation),
                'Stock reserved successfully',
                201
            );
        } catch (StockDocumentException $e) {
            return $e->render();
        }
    }

    public function release(string $reservationId): JsonResponse
    {
        Gate::authorize('release', StockReservation::class);

        try {
            $reservation = $this->releaseService->execute($reservationId);

            return $this->successResponse(
                new StockReservationResource($reservation),
                'Stock reservation released successfully',
                200
            );
        } catch (StockDocumentException $e) {
            return $e->render();
        }
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', StockReservation::class);

        $reservations = $this->queryService->paginate($request->all());

        return $this->paginatedResponse(
            StockReservationResource::collection($reservations),
            'Stock reservations retrieved successfully'
        );
    }

    public function show(string $reservationId): JsonResponse
    {
        Gate::authorize('view', StockReservation::class);

        try {
            $reservation = $this->queryService->find($reservationId);

            return $this->successResponse(
                new StockReservationResource($reservation),
                'Stock reservation retrieved successfully'
            );
        } catch (StockDocumentException $e) {
            return $e->render();
        }
    }
}
