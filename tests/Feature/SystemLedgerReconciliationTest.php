<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\AuthenticationAudit\Models\User;
use Modules\InventoryTransaction\Enums\StockDocumentStatus;
use Modules\InventoryTransaction\Enums\StockDocumentType;
use Modules\InventoryTransaction\Enums\StockReservationStatus;
use Modules\InventoryTransaction\Models\SerialNumber;
use Modules\InventoryTransaction\Models\StockBalance;
use Modules\InventoryTransaction\Models\StockDocument;
use Modules\InventoryTransaction\Models\StockDocumentLine;
use Modules\InventoryTransaction\Models\StockLot;
use Modules\InventoryTransaction\Models\StockLotBalance;
use Modules\InventoryTransaction\Models\StockMovement;
use Modules\InventoryTransaction\Models\StockReservation;
use Modules\InventoryTransaction\Services\PostStockDocumentService;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;
use Tests\TestCase;

class SystemLedgerReconciliationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected Organization $org;
    protected Warehouse $warehouse;
    protected WarehouseLocation $locA;
    protected WarehouseLocation $locB;
    protected Goods $goods;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@test.com')->first();
        $this->org = Organization::where('name', 'Global Supply Co.')->first();

        $this->warehouse = Warehouse::where('organization_id', $this->org->id)->where('code', 'MAIN')->first();
        $this->locA = WarehouseLocation::where('warehouse_id', $this->warehouse->id)->where('code', 'A-01-01')->first();
        $this->locB = WarehouseLocation::where('warehouse_id', $this->warehouse->id)->where('code', 'A-01-02')->first();
        $this->goods = Goods::where('organization_id', $this->org->id)->where('is_lot_tracked', false)->where('is_serial_tracked', false)->first();
    }

    /**
     * Requirement 65: SUM(stock_movements.quantity_delta) == stock_balances.on_hand across posted lifecycle
     */
    public function test_ledger_movement_sum_reconciles_with_stock_balance_on_hand(): void
    {
        $unique = uniqid();
        $product = \Modules\MasterData\Models\Product::where('organization_id', $this->org->id)->first();
        $unit = \Modules\MasterData\Models\Unit::where('organization_id', $this->org->id)->first();

        $isolatedGoods = Goods::create([
            'organization_id' => $this->org->id,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'sku' => "RECON-SKU-{$unique}",
            'name' => "Recon Goods {$unique}",
            'pack_size' => 1,
            'is_lot_tracked' => false,
            'is_serial_tracked' => false,
            'is_active' => true,
        ]);

        $membership = \Modules\AuthenticationAudit\Models\UserOrganization::where('user_id', $this->admin->id)->where('organization_id', $this->org->id)->first();
        $role = \Modules\AuthenticationAudit\Models\Role::find($membership->role_id);
        app()->instance(\Modules\AuthenticationAudit\Services\OrganizationContext::class, new \Modules\AuthenticationAudit\Services\OrganizationContext($this->admin, $this->org, $membership, $role));

        // 1. Post Receive of 100
        $docRcv = StockDocument::create([
            'organization_id' => $this->org->id,
            'document_no' => 'RCV-RECON-' . uniqid(),
            'document_type' => StockDocumentType::RECEIVE,
            'status' => StockDocumentStatus::APPROVED,
            'destination_warehouse_id' => $this->warehouse->id,
            'destination_location_id' => $this->locA->id,
            'created_by' => $this->admin->id,
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);
        StockDocumentLine::create(['document_id' => $docRcv->id, 'goods_id' => $isolatedGoods->id, 'line_number' => 1, 'quantity' => 100]);

        $postService = app(PostStockDocumentService::class);
        $postService->execute($docRcv->id, 'idem-rcv-' . uniqid(), []);

        // 2. Post Issue of 25
        $docIss = StockDocument::create([
            'organization_id' => $this->org->id,
            'document_no' => 'ISS-RECON-' . uniqid(),
            'document_type' => StockDocumentType::ISSUE,
            'status' => StockDocumentStatus::APPROVED,
            'source_warehouse_id' => $this->warehouse->id,
            'source_location_id' => $this->locA->id,
            'created_by' => $this->admin->id,
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);
        StockDocumentLine::create(['document_id' => $docIss->id, 'goods_id' => $isolatedGoods->id, 'line_number' => 1, 'quantity' => 25]);
        $postService->execute($docIss->id, 'idem-iss-' . uniqid(), []);

        // 3. Verify Reconciliation: SUM(movements) == on_hand (100 - 25 = 75)
        $balance = StockBalance::where('organization_id', $this->org->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->where('location_id', $this->locA->id)
            ->where('goods_id', $isolatedGoods->id)
            ->first();

        $movementSum = StockMovement::where('organization_id', $this->org->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->where('location_id', $this->locA->id)
            ->where('goods_id', $isolatedGoods->id)
            ->sum('quantity_delta');

        $this->assertEquals(
            (float) $balance->on_hand,
            (float) $movementSum,
            "Stock balance on_hand ({$balance->on_hand}) does not match movement sum ({$movementSum})"
        );
    }

    /**
     * Requirement 66: For lot-tracked goods, SUM(stock_lot_balances.on_hand) == stock_balances.on_hand
     */
    public function test_lot_balances_sum_reconciles_with_stock_balance_on_hand(): void
    {
        $lotGoods = Goods::where('organization_id', $this->org->id)
            ->where('is_lot_tracked', true)
            ->get();

        foreach ($lotGoods as $goods) {
            $balances = StockBalance::where('organization_id', $this->org->id)
                ->where('goods_id', $goods->id)
                ->get();

            foreach ($balances as $balance) {
                $lotSum = StockLotBalance::join('stock_lots', 'stock_lot_balances.lot_id', '=', 'stock_lots.id')
                    ->where('stock_lot_balances.organization_id', $balance->organization_id)
                    ->where('stock_lot_balances.warehouse_id', $balance->warehouse_id)
                    ->where('stock_lot_balances.location_id', $balance->location_id)
                    ->where('stock_lots.goods_id', $balance->goods_id)
                    ->sum('stock_lot_balances.on_hand');

                $this->assertEquals(
                    (float) $balance->on_hand,
                    (float) $lotSum,
                    "Lot balance sum ({$lotSum}) does not match stock balance on_hand ({$balance->on_hand}) for lot-tracked goods {$goods->sku} at location {$balance->location_id}"
                );
            }
        }
    }

    /**
     * Requirement 68: SUM(ACTIVE reservations.quantity) == stock_balances.reserved
     */
    public function test_active_reservations_sum_reconciles_with_stock_balance_reserved(): void
    {
        $balances = StockBalance::where('organization_id', $this->org->id)->get();

        foreach ($balances as $balance) {
            $resSum = StockReservation::where('organization_id', $balance->organization_id)
                ->where('warehouse_id', $balance->warehouse_id)
                ->where('location_id', $balance->location_id)
                ->where('goods_id', $balance->goods_id)
                ->where('status', StockReservationStatus::ACTIVE)
                ->sum('quantity');

            $this->assertEquals(
                (float) $balance->reserved,
                (float) $resSum,
                "Stock balance reserved ({$balance->reserved}) does not match active reservation sum ({$resSum}) for goods ID {$balance->goods_id}"
            );
        }
    }

    /**
     * Requirement 70 & 71: Serial-tracked Goods IN_STOCK + RESERVED count == location on_hand
     */
    public function test_serial_count_invariants_reconcile_with_stock_balances(): void
    {
        $serialGoods = Goods::where('organization_id', $this->org->id)
            ->where('is_serial_tracked', true)
            ->get();

        foreach ($serialGoods as $goods) {
            $balances = StockBalance::where('organization_id', $this->org->id)
                ->where('goods_id', $goods->id)
                ->get();

            foreach ($balances as $balance) {
                $inStockCount = SerialNumber::where('organization_id', $balance->organization_id)
                    ->where('warehouse_id', $balance->warehouse_id)
                    ->where('location_id', $balance->location_id)
                    ->where('goods_id', $balance->goods_id)
                    ->whereIn('status', ['IN_STOCK', 'RESERVED'])
                    ->count();

                $this->assertEquals(
                    (float) $balance->on_hand,
                    (float) $inStockCount,
                    "Serial in-stock/reserved count ({$inStockCount}) does not match on_hand ({$balance->on_hand}) for serial goods {$goods->sku} at location {$balance->location_id}"
                );
            }
        }
    }

    /**
     * Requirement 74: Orphan detection across all inventory transaction entities
     */
    public function test_zero_orphaned_records_across_all_tables(): void
    {
        $orphanLines = DB::table('stock_document_lines')
            ->leftJoin('stock_documents', 'stock_document_lines.document_id', '=', 'stock_documents.id')
            ->whereNull('stock_documents.id')
            ->count();
        $this->assertEquals(0, $orphanLines, "Found {$orphanLines} orphaned stock_document_lines");

        $orphanMovements = DB::table('stock_movements')
            ->leftJoin('stock_documents', 'stock_movements.document_id', '=', 'stock_documents.id')
            ->whereNull('stock_documents.id')
            ->count();
        $this->assertEquals(0, $orphanMovements, "Found {$orphanMovements} orphaned stock_movements without document");

        $orphanLotBalances = DB::table('stock_lot_balances')
            ->leftJoin('stock_lots', 'stock_lot_balances.lot_id', '=', 'stock_lots.id')
            ->whereNull('stock_lots.id')
            ->count();
        $this->assertEquals(0, $orphanLotBalances, "Found {$orphanLotBalances} orphaned stock_lot_balances without stock_lots");

        $mismatchedSerials = DB::table('serial_numbers')
            ->join('stock_lots', 'serial_numbers.lot_id', '=', 'stock_lots.id')
            ->whereColumn('serial_numbers.goods_id', '!=', 'stock_lots.goods_id')
            ->count();
        $this->assertEquals(0, $mismatchedSerials, "Found {$mismatchedSerials} serial_numbers with mismatched goods/lot");
    }

    /**
     * Requirement 75: Cross-Organization consistency audit
     */
    public function test_cross_organization_data_consistency(): void
    {
        $mismatchedDocLines = DB::table('stock_document_lines')
            ->join('stock_documents', 'stock_document_lines.document_id', '=', 'stock_documents.id')
            ->join('goods', 'stock_document_lines.goods_id', '=', 'goods.id')
            ->whereColumn('stock_documents.organization_id', '!=', 'goods.organization_id')
            ->count();
        $this->assertEquals(0, $mismatchedDocLines, "Found {$mismatchedDocLines} document lines referencing goods from foreign organization");

        $mismatchedBalances = DB::table('stock_balances')
            ->join('goods', 'stock_balances.goods_id', '=', 'goods.id')
            ->join('warehouses', 'stock_balances.warehouse_id', '=', 'warehouses.id')
            ->join('warehouse_locations', 'stock_balances.location_id', '=', 'warehouse_locations.id')
            ->where(function ($q) {
                $q->whereColumn('stock_balances.organization_id', '!=', 'goods.organization_id')
                    ->orWhereColumn('stock_balances.organization_id', '!=', 'warehouses.organization_id')
                    ->orWhereColumn('stock_balances.organization_id', '!=', 'warehouse_locations.organization_id');
            })
            ->count();
        $this->assertEquals(0, $mismatchedBalances, "Found {$mismatchedBalances} stock_balances with inconsistent organization references");
    }
}
