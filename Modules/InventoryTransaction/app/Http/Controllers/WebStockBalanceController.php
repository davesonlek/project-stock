<?php

namespace Modules\InventoryTransaction\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\InventoryTransaction\Models\StockBalance;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;

class WebStockBalanceController extends Controller
{
    public function index(Request $request): View
    {
        $orgId = session('current_organization_id');

        $query = StockBalance::where('organization_id', $orgId)
            ->with(['goods.product', 'goods.unit', 'warehouse', 'location']);

        if ($search = $request->query('search')) {
            $query->whereHas('goods', function ($q) use ($search) {
                $q->where('sku', 'ilike', "%{$search}%")
                    ->orWhere('name', 'ilike', "%{$search}%")
                    ->orWhereHas('product', function ($pq) use ($search) {
                        $pq->where('name', 'ilike', "%{$search}%")
                            ->orWhere('sku', 'ilike', "%{$search}%");
                    });
            });
        }

        if ($warehouseId = $request->query('warehouse_id')) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($locationId = $request->query('location_id')) {
            $query->where('location_id', $locationId);
        }

        if ($goodsId = $request->query('goods_id')) {
            $query->where('goods_id', $goodsId);
        }

        if ($filterStock = $request->query('stock_filter')) {
            if ($filterStock === 'has_stock') {
                $query->where('on_hand', '>', 0);
            } elseif ($filterStock === 'zero_stock') {
                $query->where('on_hand', '<=', 0);
            } elseif ($filterStock === 'reserved_only') {
                $query->where('reserved', '>', 0);
            }
        }

        $perPage = min((int) $request->query('per_page', 20), 100);
        $balances = $query->orderBy('warehouse_id')->orderBy('location_id')->paginate($perPage);

        $warehouses = Warehouse::forOrganization($orgId)->where('is_active', true)->orderBy('name')->get();
        $locations = WarehouseLocation::whereHas('warehouse', function ($q) use ($orgId) {
            $q->where('organization_id', $orgId);
        })->where('is_active', true)->orderBy('code')->get();

        return view('inventory.stock.index', compact('balances', 'warehouses', 'locations'));
    }
}
