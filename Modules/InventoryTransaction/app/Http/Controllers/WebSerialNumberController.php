<?php

namespace Modules\InventoryTransaction\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\InventoryTransaction\Models\SerialNumber;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;

class WebSerialNumberController extends Controller
{
    public function index(Request $request): View
    {
        $orgId = session('current_organization_id');

        $query = SerialNumber::where('organization_id', $orgId)
            ->with(['goods.product', 'goods.unit', 'warehouse', 'location', 'stockLot']);

        if ($search = $request->query('search')) {
            $query->where('serial_no', 'ilike', "%{$search}%");
        }

        if ($goodsId = $request->query('goods_id')) {
            $query->where('goods_id', $goodsId);
        }

        if ($warehouseId = $request->query('warehouse_id')) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($locationId = $request->query('location_id')) {
            $query->where('location_id', $locationId);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $perPage = min((int) $request->query('per_page', 20), 100);
        $serials = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $warehouses = Warehouse::forOrganization($orgId)->where('is_active', true)->orderBy('name')->get();
        $locations = WarehouseLocation::whereHas('warehouse', function ($q) use ($orgId) {
            $q->where('organization_id', $orgId);
        })->where('is_active', true)->orderBy('code')->get();
        $goodsList = Goods::forOrganization($orgId)->where('is_serial_tracked', true)->with(['product', 'unit'])->get();

        return view('inventory.serials.index', compact('serials', 'warehouses', 'locations', 'goodsList'));
    }
}
