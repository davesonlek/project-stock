<?php

namespace Modules\InventoryTransaction\Services;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\AuthenticationAudit\Services\OrganizationContext;
use Modules\InventoryTransaction\Models\InventoryDailySummary;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;

class InventoryDailySummaryService
{
    public function __construct(
        protected OrganizationContext $context
    ) {}

    public function refreshToday(): void
    {
        $this->refreshRange(
            now('Asia/Bangkok')->startOfDay(),
            now('Asia/Bangkok')->startOfDay()
        );
    }

    public function refreshRange(CarbonInterface $dateFrom, CarbonInterface $dateTo): void
    {
        $orgId = $this->context->organizationId();
        $from = $dateFrom->copy()->timezone('Asia/Bangkok')->toDateString();
        $to = $dateTo->copy()->timezone('Asia/Bangkok')->toDateString();

        DB::transaction(function () use ($orgId, $from, $to): void {
            DB::statement(
                'CALL sp_refresh_inventory_daily_summary(?, ?, ?)',
                [$orgId, $from, $to]
            );
        });
    }

    public function getTodayDashboardData(): array
    {
        $orgId = $this->context->organizationId();
        $today = now('Asia/Bangkok')->toDateString();

        $movementRow = DB::table('inventory_daily_summaries')
            ->where('organization_id', $orgId)
            ->where('summary_date', $today)
            ->selectRaw('
                COALESCE(SUM(receive_qty), 0)::numeric(18,4) as receive_qty,
                COALESCE(SUM(issue_qty), 0)::numeric(18,4) as issue_qty,
                COALESCE(SUM(transfer_in_qty), 0)::numeric(18,4) as transfer_in_qty,
                COALESCE(SUM(transfer_out_qty), 0)::numeric(18,4) as transfer_out_qty,
                COALESCE(SUM(adjust_in_qty), 0)::numeric(18,4) as adjust_in_qty,
                COALESCE(SUM(adjust_out_qty), 0)::numeric(18,4) as adjust_out_qty,
                COALESCE(SUM(reversal_net_qty), 0)::numeric(18,4) as reversal_net_qty,
                COALESCE(SUM(net_movement_qty), 0)::numeric(18,4) as net_movement_qty,
                COALESCE(SUM(movement_count), 0)::bigint as movement_count,
                MAX(refreshed_at) as last_refreshed_at
            ')
            ->first();

        $stockByUnit = DB::table('vw_stock_availability')
            ->where('organization_id', $orgId)
            ->selectRaw('
                unit_code,
                COALESCE(SUM(on_hand), 0)::numeric(18,4) as on_hand,
                COALESCE(SUM(reserved), 0)::numeric(18,4) as reserved,
                COALESCE(SUM(available), 0)::numeric(18,4) as available
            ')
            ->groupBy('unit_code')
            ->orderBy('unit_code')
            ->get();

        $reconciliationMismatchCount = (int) DB::table('vw_inventory_reconciliation')
            ->where('organization_id', $orgId)
            ->where('reconciliation_status', 'MISMATCH')
            ->count();

        $activeReservationCount = (int) DB::table('vw_active_reservations')
            ->where('organization_id', $orgId)
            ->count();

        $expiredLotCount = (int) DB::table('vw_lot_expiry')
            ->where('organization_id', $orgId)
            ->where('expiry_status', 'EXPIRED')
            ->count();

        $expiringWithin30DaysCount = (int) DB::table('vw_lot_expiry')
            ->where('organization_id', $orgId)
            ->whereIn('expiry_status', ['EXPIRING_7_DAYS', 'EXPIRING_30_DAYS'])
            ->count();

        $doughnutTransfer = bcadd(
            (string) ($movementRow->transfer_in_qty ?? '0'),
            (string) ($movementRow->transfer_out_qty ?? '0'),
            4
        );
        $doughnutAdjustment = bcadd(
            (string) ($movementRow->adjust_in_qty ?? '0'),
            (string) ($movementRow->adjust_out_qty ?? '0'),
            4
        );

        $topGoods = DB::table('inventory_daily_summaries as ids')
            ->join('goods as g', 'g.id', '=', 'ids.goods_id')
            ->join('products as p', 'p.id', '=', 'g.product_id')
            ->where('ids.organization_id', $orgId)
            ->where('ids.summary_date', $today)
            ->selectRaw("
                ids.goods_id,
                p.sku,
                p.name as goods_name,
                (
                    COALESCE(SUM(ids.receive_qty), 0)
                    + COALESCE(SUM(ids.issue_qty), 0)
                    + COALESCE(SUM(ids.transfer_in_qty), 0)
                    + COALESCE(SUM(ids.transfer_out_qty), 0)
                    + COALESCE(SUM(ids.adjust_in_qty), 0)
                    + COALESCE(SUM(ids.adjust_out_qty), 0)
                )::numeric(18,4) as gross_movement_qty
            ")
            ->groupBy('ids.goods_id', 'p.sku', 'p.name')
            ->orderByDesc('gross_movement_qty')
            ->limit(10)
            ->get();

        $byWarehouse = DB::table('inventory_daily_summaries as ids')
            ->join('warehouses as w', 'w.id', '=', 'ids.warehouse_id')
            ->where('ids.organization_id', $orgId)
            ->where('ids.summary_date', $today)
            ->selectRaw("
                ids.warehouse_id,
                w.code as warehouse_code,
                w.name as warehouse_name,
                (
                    COALESCE(SUM(ids.receive_qty), 0)
                    + COALESCE(SUM(ids.issue_qty), 0)
                    + COALESCE(SUM(ids.transfer_in_qty), 0)
                    + COALESCE(SUM(ids.transfer_out_qty), 0)
                    + COALESCE(SUM(ids.adjust_in_qty), 0)
                    + COALESCE(SUM(ids.adjust_out_qty), 0)
                )::numeric(18,4) as gross_movement_qty
            ")
            ->groupBy('ids.warehouse_id', 'w.code', 'w.name')
            ->orderByDesc('gross_movement_qty')
            ->get();

        return [
            'summary_date' => $today,
            'movement' => [
                'receive_qty' => (string) ($movementRow->receive_qty ?? '0.0000'),
                'issue_qty' => (string) ($movementRow->issue_qty ?? '0.0000'),
                'transfer_in_qty' => (string) ($movementRow->transfer_in_qty ?? '0.0000'),
                'transfer_out_qty' => (string) ($movementRow->transfer_out_qty ?? '0.0000'),
                'adjust_in_qty' => (string) ($movementRow->adjust_in_qty ?? '0.0000'),
                'adjust_out_qty' => (string) ($movementRow->adjust_out_qty ?? '0.0000'),
                'reversal_net_qty' => (string) ($movementRow->reversal_net_qty ?? '0.0000'),
                'net_movement_qty' => (string) ($movementRow->net_movement_qty ?? '0.0000'),
                'movement_count' => (int) ($movementRow->movement_count ?? 0),
                'last_refreshed_at' => $movementRow->last_refreshed_at ?? null,
            ],
            'reconciliation_mismatch_count' => $reconciliationMismatchCount,
            'active_reservation_count' => $activeReservationCount,
            'expired_lot_count' => $expiredLotCount,
            'expiring_within_30_days_count' => $expiringWithin30DaysCount,
            'stock_by_unit' => $stockByUnit,
            'charts' => [
                'doughnut' => [
                    'receive' => (string) ($movementRow->receive_qty ?? '0.0000'),
                    'issue' => (string) ($movementRow->issue_qty ?? '0.0000'),
                    'transfer' => $doughnutTransfer,
                    'adjustment' => $doughnutAdjustment,
                ],
                'top_goods' => $topGoods,
                'by_warehouse' => $byWarehouse,
            ],
        ];
    }

    public function getReportData(array $filters): LengthAwarePaginator
    {
        $orgId = $this->context->organizationId();

        $dateFrom = $filters['date_from'] ?? now('Asia/Bangkok')->toDateString();
        $dateTo = $filters['date_to'] ?? now('Asia/Bangkok')->toDateString();
        $perPage = min((int) ($filters['per_page'] ?? 25), 100);

        $query = InventoryDailySummary::query()
            ->forOrganization($orgId)
            ->with(['warehouse', 'location', 'goods.product'])
            ->where('summary_date', '>=', $dateFrom)
            ->where('summary_date', '<=', $dateTo);

        if (! empty($filters['warehouse_id'])) {
            $warehouseId = (int) $filters['warehouse_id'];
            $this->assertWarehouseInOrganization($warehouseId, $orgId);
            $query->where('warehouse_id', $warehouseId);
        }

        if (! empty($filters['location_id'])) {
            $locationId = (int) $filters['location_id'];
            $this->assertLocationInOrganization($locationId, $orgId);
            $query->where('location_id', $locationId);
        }

        if (! empty($filters['goods_id'])) {
            $goodsId = (int) $filters['goods_id'];
            $this->assertGoodsInOrganization($goodsId, $orgId);
            $query->where('goods_id', $goodsId);
        }

        return $query
            ->orderByDesc('summary_date')
            ->orderBy('warehouse_id')
            ->orderBy('location_id')
            ->orderBy('goods_id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function getLastRefreshedAt(?string $dateFrom = null, ?string $dateTo = null): ?string
    {
        $orgId = $this->context->organizationId();

        $query = InventoryDailySummary::query()->forOrganization($orgId);

        if ($dateFrom !== null) {
            $query->where('summary_date', '>=', $dateFrom);
        }

        if ($dateTo !== null) {
            $query->where('summary_date', '<=', $dateTo);
        }

        $value = $query->max('refreshed_at');

        return $value ? (string) $value : null;
    }

    protected function assertWarehouseInOrganization(int $warehouseId, string $organizationId): void
    {
        if (! Warehouse::forOrganization($organizationId)->where('id', $warehouseId)->exists()) {
            throw ValidationException::withMessages([
                'warehouse_id' => ['The selected warehouse is not valid for your organization.'],
            ]);
        }
    }

    protected function assertLocationInOrganization(int $locationId, string $organizationId): void
    {
        if (! WarehouseLocation::query()->where('id', $locationId)->where('organization_id', $organizationId)->exists()) {
            throw ValidationException::withMessages([
                'location_id' => ['The selected location is not valid for your organization.'],
            ]);
        }
    }

    protected function assertGoodsInOrganization(int $goodsId, string $organizationId): void
    {
        if (! Goods::forOrganization($organizationId)->where('id', $goodsId)->exists()) {
            throw ValidationException::withMessages([
                'goods_id' => ['The selected goods item is not valid for your organization.'],
            ]);
        }
    }
}
