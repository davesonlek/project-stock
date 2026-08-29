<?php

namespace Modules\InventoryTransaction\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\InventoryTransaction\Models\StockLotBalance;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;

class WebStockLotController extends Controller
{
    public function index(Request $request): View
    {
        $orgId = session('current_organization_id');

        $query = StockLotBalance::where('organization_id', $orgId)
            ->with(['stockLot.goods.product', 'stockLot.goods.unit', 'warehouse', 'location']);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('stockLot', function ($lq) use ($search) {
                    $lq->where('lot_no', 'ilike', "%{$search}%")
                        ->orWhereHas('goods', function ($gq) use ($search) {
                            $gq->where('sku', 'ilike', "%{$search}%")
                                ->orWhere('name', 'ilike', "%{$search}%");
                        });
                });
            });
        }

        if ($warehouseId = $request->query('warehouse_id')) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($locationId = $request->query('location_id')) {
            $query->where('location_id', $locationId);
        }

        if ($statusFilter = $request->query('status_filter')) {
            $today = Carbon::today()->toDateString();
            $thirtyDays = Carbon::today()->addDays(30)->toDateString();

            if ($statusFilter === 'EXPIRED') {
                $query->whereHas('stockLot', function ($lq) use ($today) {
                    $lq->where('expired_at', '<', $today);
                });
            } elseif ($statusFilter === 'NEAR_EXPIRY') {
                $query->whereHas('stockLot', function ($lq) use ($today, $thirtyDays) {
                    $lq->where('expired_at', '>=', $today)->where('expired_at', '<=', $thirtyDays);
                });
            } elseif ($statusFilter === 'ACTIVE') {
                $query->whereHas('stockLot', function ($lq) use ($thirtyDays) {
                    $lq->where('expired_at', '>', $thirtyDays);
                });
            }
        }

        $perPage = min((int) $request->query('per_page', 20), 100);
        $lotBalances = $query->orderBy('warehouse_id')->orderBy('location_id')->paginate($perPage);

        $warehouses = Warehouse::forOrganization($orgId)->where('is_active', true)->orderBy('name')->get();
        $locations = WarehouseLocation::whereHas('warehouse', function ($q) use ($orgId) {
            $q->where('organization_id', $orgId);
        })->where('is_active', true)->orderBy('code')->get();

        return view('inventory.lots.index', compact('lotBalances', 'warehouses', 'locations'));
    }
}
