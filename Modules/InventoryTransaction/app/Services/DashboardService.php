<?php

namespace Modules\InventoryTransaction\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\AuthenticationAudit\Services\OrganizationContext;
use Modules\InventoryTransaction\Enums\StockDocumentStatus;
use Modules\InventoryTransaction\Enums\StockDocumentType;
use Modules\InventoryTransaction\Models\StockBalance;
use Modules\InventoryTransaction\Models\StockDocument;
use Modules\InventoryTransaction\Models\StockLot;
use Modules\InventoryTransaction\Models\StockMovement;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Product;

class DashboardService
{
    public function __construct(
        protected OrganizationContext $context
    ) {}

    public function getDashboardMetrics(): array
    {
        $orgId = $this->context->organizationId();
        $today = Carbon::today()->toDateString();
        $thirtyDaysAhead = Carbon::today()->addDays(30)->toDateString();

        // 1. Core KPIs
        $totalProducts = Product::where('organization_id', $orgId)->count();
        $totalGoods = Goods::where('organization_id', $orgId)->count();

        $balanceAggregates = StockBalance::where('organization_id', $orgId)
            ->selectRaw('
                COALESCE(SUM(on_hand), 0) as total_on_hand,
                COALESCE(SUM(reserved), 0) as total_reserved,
                COALESCE(SUM(on_hand - reserved), 0) as total_available
            ')
            ->first();

        $totalOnHand = $balanceAggregates->total_on_hand ?? '0.0000';
        $totalReserved = $balanceAggregates->total_reserved ?? '0.0000';
        $totalAvailable = $balanceAggregates->total_available ?? '0.0000';

        $lowStockCount = StockBalance::where('organization_id', $orgId)
            ->where('on_hand', '<=', 0)
            ->count();

        $nearExpiryCount = StockLot::where('organization_id', $orgId)
            ->whereNotNull('expired_at')
            ->where('expired_at', '>=', $today)
            ->where('expired_at', '<=', $thirtyDaysAhead)
            ->count();

        $expiredLotCount = StockLot::where('organization_id', $orgId)
            ->whereNotNull('expired_at')
            ->where('expired_at', '<', $today)
            ->count();

        $todayReceiveCount = StockDocument::where('organization_id', $orgId)
            ->where('document_type', StockDocumentType::RECEIVE)
            ->whereDate('created_at', $today)
            ->count();

        $todayIssueCount = StockDocument::where('organization_id', $orgId)
            ->where('document_type', StockDocumentType::ISSUE)
            ->whereDate('created_at', $today)
            ->count();

        $todayTransferCount = StockDocument::where('organization_id', $orgId)
            ->where('document_type', StockDocumentType::TRANSFER)
            ->whereDate('created_at', $today)
            ->count();

        $pendingDocumentsCount = StockDocument::where('organization_id', $orgId)
            ->where('status', StockDocumentStatus::PENDING)
            ->count();

        // 2. Document Status Breakdown
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

        // 3. Recent Movements (last 15)
        $recentMovements = StockMovement::where('organization_id', $orgId)
            ->with(['goods.product', 'goods.unit', 'warehouse', 'location', 'performer', 'stockLot'])
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit(15)
            ->get();

        return [
            'kpis' => [
                'total_products' => $totalProducts,
                'total_goods' => $totalGoods,
                'total_on_hand' => $totalOnHand,
                'total_reserved' => $totalReserved,
                'total_available' => $totalAvailable,
                'low_stock_items' => $lowStockCount,
                'near_expiry_lots' => $nearExpiryCount,
                'expired_lots' => $expiredLotCount,
                'today_receive' => $todayReceiveCount,
                'today_issue' => $todayIssueCount,
                'today_transfer' => $todayTransferCount,
                'pending_documents' => $pendingDocumentsCount,
            ],
            'document_statuses' => $documentStatuses,
            'recent_movements' => $recentMovements,
        ];
    }
}
