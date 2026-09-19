<?php

namespace Modules\InventoryTransaction\Services;

use Illuminate\Support\Facades\DB;
use Modules\AuthenticationAudit\Services\OrganizationContext;
use Modules\InventoryTransaction\Enums\StockDocumentStatus;
use Modules\InventoryTransaction\Models\StockDocument;
use Modules\InventoryTransaction\Models\StockMovement;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Product;

class DashboardService
{
    public function __construct(
        protected OrganizationContext $context,
        protected InventoryDailySummaryService $dailySummaryService
    ) {}

    public function getDashboardMetrics(): array
    {
        $orgId = $this->context->organizationId();

        $totalProducts = Product::where('organization_id', $orgId)->count();
        $totalGoods = Goods::where('organization_id', $orgId)->count();

        $dailySummary = $this->dailySummaryService->getTodayDashboardData();

        $zeroStockCount = (int) DB::table('vw_stock_availability')
            ->where('organization_id', $orgId)
            ->where('is_zero_stock', true)
            ->count();

        $pendingDocumentsCount = StockDocument::where('organization_id', $orgId)
            ->where('status', StockDocumentStatus::PENDING)
            ->count();

        $statusCountsRaw = StockDocument::where('organization_id', $orgId)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $documentStatuses = [
            'DRAFT' => $statusCountsRaw['DRAFT'] ?? 0,
            'PENDING' => $statusCountsRaw['PENDING'] ?? 0,
            'APPROVED' => $statusCountsRaw['APPROVED'] ?? 0,
            'POSTED' => $statusCountsRaw['POSTED'] ?? 0,
            'REVERSED' => $statusCountsRaw['REVERSED'] ?? 0,
            'CANCELLED' => $statusCountsRaw['CANCELLED'] ?? 0,
        ];

        $recentMovements = StockMovement::where('organization_id', $orgId)
            ->with(['goods.product', 'goods.unit', 'warehouse', 'location', 'performer', 'stockLot'])
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit(15)
            ->get();

        $movement = $dailySummary['movement'];

        return [
            'kpis' => [
                'total_products' => $totalProducts,
                'total_goods' => $totalGoods,
                'low_stock_items' => $zeroStockCount,
                'pending_documents' => $pendingDocumentsCount,
                'receive_qty' => $movement['receive_qty'],
                'issue_qty' => $movement['issue_qty'],
                'transfer_in_qty' => $movement['transfer_in_qty'],
                'transfer_out_qty' => $movement['transfer_out_qty'],
                'adjust_in_qty' => $movement['adjust_in_qty'],
                'adjust_out_qty' => $movement['adjust_out_qty'],
                'reversal_net_qty' => $movement['reversal_net_qty'],
                'net_movement_qty' => $movement['net_movement_qty'],
                'movement_count' => $movement['movement_count'],
                'last_refreshed_at' => $movement['last_refreshed_at'],
                'reconciliation_mismatch_count' => $dailySummary['reconciliation_mismatch_count'],
                'active_reservation_count' => $dailySummary['active_reservation_count'],
                'expired_lot_count' => $dailySummary['expired_lot_count'],
                'expiring_within_30_days_count' => $dailySummary['expiring_within_30_days_count'],
            ],
            'stock_by_unit' => $dailySummary['stock_by_unit'],
            'daily_summary_date' => $dailySummary['summary_date'],
            'chart_data' => $dailySummary['charts'],
            'document_statuses' => $documentStatuses,
            'recent_movements' => $recentMovements,
        ];
    }
}
