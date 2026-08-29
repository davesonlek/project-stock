<?php

namespace Modules\InventoryTransaction\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\InventoryTransaction\Exceptions\StockDocumentException;
use Modules\InventoryTransaction\Models\StockReservation;
use Modules\InventoryTransaction\Services\QueryStockReservationService;
use Modules\InventoryTransaction\Services\ReleaseStockReservationService;
use Modules\MasterData\Models\Warehouse;

class WebReservationController extends Controller
{
    public function __construct(
        protected QueryStockReservationService $queryService,
        protected ReleaseStockReservationService $releaseService
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', StockReservation::class);

        $reservations = $this->queryService->paginate([
            'status' => $request->query('status'),
            'warehouse_id' => $request->query('warehouse_id'),
            'location_id' => $request->query('location_id'),
            'goods_id' => $request->query('goods_id'),
            'document_id' => $request->query('document_id'),
            'per_page' => $request->query('per_page', 20),
        ]);

        $orgId = session('current_organization_id');
        $warehouses = Warehouse::forOrganization($orgId)->where('is_active', true)->orderBy('name')->get();

        return view('inventory.reservations.index', compact('reservations', 'warehouses'));
    }

    public function release(string $id): RedirectResponse
    {
        Gate::authorize('release', StockReservation::class);

        try {
            $this->releaseService->execute($id);
            return back()->with('success', 'Reservation released successfully.');
        } catch (StockDocumentException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to release reservation: ' . $e->getMessage());
        }
    }
}
