<?php

namespace Modules\InventoryTransaction\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\AuthenticationAudit\Services\OrganizationContext;
use Modules\InventoryTransaction\Http\Requests\RefreshInventorySummaryRequest;
use Modules\InventoryTransaction\Services\InventoryDailySummaryService;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;

class InventoryDailySummaryController extends Controller
{
    public function __construct(
        protected InventoryDailySummaryService $summaryService,
        protected OrganizationContext $context
    ) {}

    public function refreshToday(): RedirectResponse
    {
        try {
            $this->summaryService->refreshToday();
            $today = now('Asia/Bangkok')->toDateString();

            return redirect()
                ->back()
                ->with('success', "Inventory daily summary refreshed for {$today}.");
        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->with('error', 'Failed to refresh today\'s inventory summary: '.$e->getMessage());
        }
    }

    public function refreshRange(RefreshInventorySummaryRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $dateFrom = Carbon::parse($validated['date_from'], 'Asia/Bangkok')->startOfDay();
        $dateTo = Carbon::parse($validated['date_to'], 'Asia/Bangkok')->startOfDay();

        try {
            $this->summaryService->refreshRange($dateFrom, $dateTo);

            return redirect()
                ->route('reports.inventory-daily-summary.index', array_filter([
                    'date_from' => $validated['date_from'],
                    'date_to' => $validated['date_to'],
                    'warehouse_id' => $request->input('warehouse_id'),
                    'location_id' => $request->input('location_id'),
                    'goods_id' => $request->input('goods_id'),
                    'per_page' => $request->input('per_page'),
                ], fn ($v) => $v !== null && $v !== ''))
                ->with('success', "Inventory daily summary refreshed from {$validated['date_from']} to {$validated['date_to']}.");
        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to refresh inventory summary: '.$e->getMessage());
        }
    }

    public function index(Request $request): View
    {
        $orgId = $this->context->organizationId();
        $today = now('Asia/Bangkok')->toDateString();

        $filters = [
            'date_from' => $request->query('date_from', $today),
            'date_to' => $request->query('date_to', $today),
            'warehouse_id' => $request->query('warehouse_id'),
            'location_id' => $request->query('location_id'),
            'goods_id' => $request->query('goods_id'),
            'per_page' => $request->query('per_page', 25),
        ];

        $summaries = $this->summaryService->getReportData($filters);
        $lastRefreshedAt = $this->summaryService->getLastRefreshedAt(
            $filters['date_from'],
            $filters['date_to']
        );

        $warehouses = Warehouse::forOrganization($orgId)->where('is_active', true)->orderBy('name')->get();
        $locations = WarehouseLocation::query()
            ->where('organization_id', $orgId)
            ->where('is_active', true)
            ->when($filters['warehouse_id'], fn ($q) => $q->where('warehouse_id', $filters['warehouse_id']))
            ->orderBy('code')
            ->get();
        $goodsItems = Goods::forOrganization($orgId)->where('is_active', true)
            ->with('product')
            ->orderBy('id')
            ->get();

        return view('reports.inventory-daily-summary', compact(
            'summaries',
            'filters',
            'lastRefreshedAt',
            'warehouses',
            'locations',
            'goodsItems'
        ));
    }
}
