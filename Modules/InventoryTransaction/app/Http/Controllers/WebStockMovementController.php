<?php

namespace Modules\InventoryTransaction\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\InventoryTransaction\Models\StockMovement;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;

class WebStockMovementController extends Controller
{
    public function index(Request $request): View
    {
        $orgId = session('current_organization_id');

        $query = StockMovement::where('organization_id', $orgId)
            ->with(['goods.product', 'goods.unit', 'warehouse', 'location', 'performer', 'stockLot', 'reversalOf', 'document']);

        if ($search = $request->query('search')) {
            $query->whereHas('goods', function ($q) use ($search) {
                $q->where('sku', 'ilike', "%{$search}%")
                    ->orWhere('name', 'ilike', "%{$search}%");
            });
        }

        if ($docId = $request->query('document_id')) {
            $query->where('document_id', $docId);
        }

        if ($movementType = $request->query('movement_type')) {
            $query->where('movement_type', $movementType);
        }

        if ($warehouseId = $request->query('warehouse_id')) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($locationId = $request->query('location_id')) {
            $query->where('location_id', $locationId);
        }

        if ($dateFrom = $request->query('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->query('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $perPage = min((int) $request->query('per_page', 20), 100);
        $movements = $query->orderBy('created_at', 'desc')->orderBy('id', 'desc')->paginate($perPage);

        $warehouses = Warehouse::forOrganization($orgId)->where('is_active', true)->orderBy('name')->get();
        $locations = WarehouseLocation::whereHas('warehouse', function ($q) use ($orgId) {
            $q->where('organization_id', $orgId);
        })->where('is_active', true)->orderBy('code')->get();

        return view('history.movements', compact('movements', 'warehouses', 'locations'));
    }
}
