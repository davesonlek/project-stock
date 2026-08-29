<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\InventoryTransaction\Enums\SerialNumberStatus;
use Modules\InventoryTransaction\Models\SerialNumber;
use Modules\InventoryTransaction\Models\StockBalance;
use Modules\InventoryTransaction\Models\StockLotBalance;
use Modules\InventoryTransaction\Models\StockMovement;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;
use Tests\TestCase;

class InventoryReconciliationTest extends TestCase
{
    use DatabaseTransactions;

    protected string $token;
    protected string $orgId;
    protected Warehouse $warehouse;
    protected WarehouseLocation $locA;
    protected WarehouseLocation $locB;
    protected Goods $stdGoods;
    protected Goods $lotGoods;
    protected ?Goods $serialGoods = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $org = Organization::where('name', 'Global Supply Co.')->first();
        $this->orgId = $org->id;

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'manager@test.com',
            'password' => 'password123',
        ]);
        $this->token = $login->json('data.access_token');

        $this->warehouse = Warehouse::where('organization_id', $this->orgId)->where('code', 'MAIN')->first();
        $this->locA = WarehouseLocation::where('warehouse_id', $this->warehouse->id)->where('code', 'A-01-01')->first();
        $this->locB = WarehouseLocation::where('warehouse_id', $this->warehouse->id)->where('code', 'A-01-02')->first();

        $product = \Modules\MasterData\Models\Product::where('organization_id', $this->orgId)->first();
        $unit = \Modules\MasterData\Models\Unit::where('organization_id', $this->orgId)->first();

        $unique = uniqid();
        $this->stdGoods = Goods::create([
            'organization_id' => $this->orgId,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'sku' => 'STD-RECON-' . $unique,
            'name' => 'Std Recon Goods ' . $unique,
            'pack_size' => 1,
            'is_lot_tracked' => false,
            'is_serial_tracked' => false,
            'is_active' => true,
        ]);
        $this->lotGoods = Goods::create([
            'organization_id' => $this->orgId,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'sku' => 'LOT-RECON-' . $unique,
            'name' => 'Lot Recon Goods ' . $unique,
            'pack_size' => 1,
            'is_lot_tracked' => true,
            'is_serial_tracked' => false,
            'is_active' => true,
        ]);
        $this->serialGoods = Goods::create([
            'organization_id' => $this->orgId,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'sku' => 'SN-RECON-' . $unique,
            'name' => 'Serial Recon Goods ' . $unique,
            'pack_size' => 1,
            'is_lot_tracked' => false,
            'is_serial_tracked' => true,
            'is_active' => true,
        ]);
    }

    protected function authHeaders(string $idempotencyKey): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'X-Organization-Id' => $this->orgId,
            'Idempotency-Key' => $idempotencyKey,
        ];
    }

    public function test_movements_sum_strictly_reconciles_with_stock_balance(): void
    {
        StockBalance::where('organization_id', $this->orgId)
            ->where('goods_id', $this->stdGoods->id)
            ->delete();

        // 1. Receive 100
        $doc1 = $this->withHeaders($this->authHeaders('RC-01'))->postJson('/api/v1/stock/documents', [
            'document_type' => 'RECEIVE',
            'destination_warehouse_id' => $this->warehouse->id,
            'destination_location_id' => $this->locA->id,
        ])->json('data.id');

        $this->withHeaders($this->authHeaders('RC-02'))->postJson("/api/v1/stock/documents/{$doc1}/lines", [
            'goods_id' => $this->stdGoods->id,
            'quantity' => 100,
        ]);
        $this->withHeaders($this->authHeaders('RC-03'))->postJson("/api/v1/stock/documents/{$doc1}/submit");
        $this->withHeaders($this->authHeaders('RC-04'))->postJson("/api/v1/stock/documents/{$doc1}/approve");
        $this->withHeaders($this->authHeaders('RC-05'))->postJson("/api/v1/stock/documents/{$doc1}/post");

        // 2. Issue 30
        $doc2 = $this->withHeaders($this->authHeaders('IS-01'))->postJson('/api/v1/stock/documents', [
            'document_type' => 'ISSUE',
            'source_warehouse_id' => $this->warehouse->id,
            'source_location_id' => $this->locA->id,
        ])->json('data.id');

        $this->withHeaders($this->authHeaders('IS-02'))->postJson("/api/v1/stock/documents/{$doc2}/lines", [
            'goods_id' => $this->stdGoods->id,
            'quantity' => 30,
        ]);
        $this->withHeaders($this->authHeaders('IS-03'))->postJson("/api/v1/stock/documents/{$doc2}/submit");
        $this->withHeaders($this->authHeaders('IS-04'))->postJson("/api/v1/stock/documents/{$doc2}/approve");
        $this->withHeaders($this->authHeaders('IS-05'))->postJson("/api/v1/stock/documents/{$doc2}/post");

        // 3. Transfer 20 from Loc A to Loc B
        $doc3 = $this->withHeaders($this->authHeaders('TR-01'))->postJson('/api/v1/stock/documents', [
            'document_type' => 'TRANSFER',
            'source_warehouse_id' => $this->warehouse->id,
            'source_location_id' => $this->locA->id,
            'destination_warehouse_id' => $this->warehouse->id,
            'destination_location_id' => $this->locB->id,
        ])->json('data.id');

        $this->withHeaders($this->authHeaders('TR-02'))->postJson("/api/v1/stock/documents/{$doc3}/lines", [
            'goods_id' => $this->stdGoods->id,
            'quantity' => 20,
        ]);
        $this->withHeaders($this->authHeaders('TR-03'))->postJson("/api/v1/stock/documents/{$doc3}/submit");
        $this->withHeaders($this->authHeaders('TR-04'))->postJson("/api/v1/stock/documents/{$doc3}/approve");
        $this->withHeaders($this->authHeaders('TR-05'))->postJson("/api/v1/stock/documents/{$doc3}/post");

        // 4. Verify Movement SUM at Loc A equals StockBalance at Loc A
        $sumMovementsLocA = StockMovement::where('organization_id', $this->orgId)
            ->where('warehouse_id', $this->warehouse->id)
            ->where('location_id', $this->locA->id)
            ->where('goods_id', $this->stdGoods->id)
            ->sum('quantity_delta');

        $balanceLocA = StockBalance::where('organization_id', $this->orgId)
            ->where('warehouse_id', $this->warehouse->id)
            ->where('location_id', $this->locA->id)
            ->where('goods_id', $this->stdGoods->id)
            ->value('on_hand');

        $this->assertEquals(number_format((float)$balanceLocA, 4, '.', ''), number_format((float)$sumMovementsLocA, 4, '.', ''));
        $this->assertEquals('50.0000', number_format((float)$balanceLocA, 4, '.', ''));

        // 5. Verify Movement SUM at Loc B equals StockBalance at Loc B
        $sumMovementsLocB = StockMovement::where('organization_id', $this->orgId)
            ->where('warehouse_id', $this->warehouse->id)
            ->where('location_id', $this->locB->id)
            ->where('goods_id', $this->stdGoods->id)
            ->sum('quantity_delta');

        $balanceLocB = StockBalance::where('organization_id', $this->orgId)
            ->where('warehouse_id', $this->warehouse->id)
            ->where('location_id', $this->locB->id)
            ->where('goods_id', $this->stdGoods->id)
            ->value('on_hand');

        $this->assertEquals('20.0000', number_format((float)$balanceLocB, 4, '.', ''));
        $this->assertEquals(number_format((float)$balanceLocB, 4, '.', ''), number_format((float)$sumMovementsLocB, 4, '.', ''));
    }

    public function test_lot_balances_sum_strictly_reconciles_with_aggregate_stock_balance(): void
    {
        StockLotBalance::where('organization_id', $this->orgId)->where('warehouse_id', $this->warehouse->id)->where('location_id', $this->locA->id)->delete();
        StockBalance::where('organization_id', $this->orgId)->where('goods_id', $this->lotGoods->id)->delete();

        $futureExp = Carbon::now()->addYears(2)->toDateString();

        // 1. Receive LOT-1 (40 qty)
        $doc1 = $this->withHeaders($this->authHeaders('LRC-01'))->postJson('/api/v1/stock/documents', [
            'document_type' => 'RECEIVE',
            'destination_warehouse_id' => $this->warehouse->id,
            'destination_location_id' => $this->locA->id,
        ])->json('data.id');

        $this->withHeaders($this->authHeaders('LRC-02'))->postJson("/api/v1/stock/documents/{$doc1}/lines", [
            'goods_id' => $this->lotGoods->id,
            'quantity' => 40,
            'lot_no' => 'LOT-REC-1',
            'expired_at' => $futureExp,
        ]);
        $this->withHeaders($this->authHeaders('LRC-03'))->postJson("/api/v1/stock/documents/{$doc1}/submit");
        $this->withHeaders($this->authHeaders('LRC-04'))->postJson("/api/v1/stock/documents/{$doc1}/approve");
        $this->withHeaders($this->authHeaders('LRC-05'))->postJson("/api/v1/stock/documents/{$doc1}/post");

        // 2. Receive LOT-2 (60 qty)
        $doc2 = $this->withHeaders($this->authHeaders('LRC-06'))->postJson('/api/v1/stock/documents', [
            'document_type' => 'RECEIVE',
            'destination_warehouse_id' => $this->warehouse->id,
            'destination_location_id' => $this->locA->id,
        ])->json('data.id');

        $this->withHeaders($this->authHeaders('LRC-07'))->postJson("/api/v1/stock/documents/{$doc2}/lines", [
            'goods_id' => $this->lotGoods->id,
            'quantity' => 60,
            'lot_no' => 'LOT-REC-2',
            'expired_at' => $futureExp,
        ]);
        $this->withHeaders($this->authHeaders('LRC-08'))->postJson("/api/v1/stock/documents/{$doc2}/submit");
        $this->withHeaders($this->authHeaders('LRC-09'))->postJson("/api/v1/stock/documents/{$doc2}/approve");
        $this->withHeaders($this->authHeaders('LRC-10'))->postJson("/api/v1/stock/documents/{$doc2}/post");

        // 3. Verify SUM(stock_lot_balances.on_hand) == stock_balances.on_hand (100 total)
        $lotBalancesSum = StockLotBalance::where('organization_id', $this->orgId)
            ->where('warehouse_id', $this->warehouse->id)
            ->where('location_id', $this->locA->id)
            ->sum('on_hand');

        $mainBalance = StockBalance::where('organization_id', $this->orgId)
            ->where('warehouse_id', $this->warehouse->id)
            ->where('location_id', $this->locA->id)
            ->where('goods_id', $this->lotGoods->id)
            ->value('on_hand');

        $this->assertEquals('100.0000', number_format((float)$mainBalance, 4, '.', ''));
        $this->assertEquals(number_format((float)$mainBalance, 4, '.', ''), number_format((float)$lotBalancesSum, 4, '.', ''));
    }

    public function test_in_stock_serials_count_strictly_reconciles_with_stock_balance(): void
    {
        StockBalance::where('organization_id', $this->orgId)->where('goods_id', $this->serialGoods->id)->delete();

        // 1. Receive 3 serials
        $doc = $this->withHeaders($this->authHeaders('SRC-01'))->postJson('/api/v1/stock/documents', [
            'document_type' => 'RECEIVE',
            'destination_warehouse_id' => $this->warehouse->id,
            'destination_location_id' => $this->locA->id,
        ])->json('data.id');

        $this->withHeaders($this->authHeaders('SRC-02'))->postJson("/api/v1/stock/documents/{$doc}/lines", [
            'goods_id' => $this->serialGoods->id,
            'quantity' => 3,
            'serials' => ['SN-REC-01', 'SN-REC-02', 'SN-REC-03'],
        ]);
        $this->withHeaders($this->authHeaders('SRC-03'))->postJson("/api/v1/stock/documents/{$doc}/submit");
        $this->withHeaders($this->authHeaders('SRC-04'))->postJson("/api/v1/stock/documents/{$doc}/approve");
        $this->withHeaders($this->authHeaders('SRC-05'))->postJson("/api/v1/stock/documents/{$doc}/post");

        // 2. Verify in_stock serials count == balance on_hand (3)
        $serialCount = SerialNumber::where('organization_id', $this->orgId)
            ->where('goods_id', $this->serialGoods->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->where('location_id', $this->locA->id)
            ->where('status', SerialNumberStatus::IN_STOCK)
            ->count();

        $balance = StockBalance::where('organization_id', $this->orgId)
            ->where('warehouse_id', $this->warehouse->id)
            ->where('location_id', $this->locA->id)
            ->where('goods_id', $this->serialGoods->id)
            ->value('on_hand');

        $this->assertEquals(3, $serialCount);
        $this->assertEquals('3.0000', number_format((float)$balance, 4, '.', ''));
    }
}
