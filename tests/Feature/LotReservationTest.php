<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\InventoryTransaction\Enums\StockReservationStatus;
use Modules\InventoryTransaction\Models\StockBalance;
use Modules\InventoryTransaction\Models\StockLot;
use Modules\InventoryTransaction\Models\StockLotBalance;
use Modules\InventoryTransaction\Models\StockReservation;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Product;
use Modules\MasterData\Models\Unit;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;
use Tests\TestCase;

class LotReservationTest extends TestCase
{
    use DatabaseTransactions;

    protected string $token;
    protected string $orgId;
    protected Warehouse $warehouse;
    protected WarehouseLocation $locA;
    protected Goods $lotGoods;

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

        $product = Product::where('organization_id', $this->orgId)->first();
        $unit = Unit::where('organization_id', $this->orgId)->first();

        $this->lotGoods = Goods::create([
            'organization_id' => $this->orgId,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'sku' => 'LOT-RES-' . uniqid(),
            'name' => 'Lot Res Goods',
            'pack_size' => 1,
            'is_lot_tracked' => true,
            'is_serial_tracked' => false,
            'is_active' => true,
        ]);
    }

    protected function authHeaders(string $key = 'LOT-RES-001'): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'X-Organization-Id' => $this->orgId,
            'Idempotency-Key' => $key,
        ];
    }

    public function test_lot_reservation_lifecycle_reserve_release_consume(): void
    {
        $futureExp = Carbon::now()->addYear()->toDateString();
        $lot = StockLot::create([
            'organization_id' => $this->orgId,
            'goods_id' => $this->lotGoods->id,
            'lot_no' => 'LOT-RES-A',
            'expired_at' => $futureExp,
        ]);

        $lotBal = StockLotBalance::create([
            'organization_id' => $this->orgId,
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->locA->id,
            'lot_id' => $lot->id,
            'on_hand' => '100.0000',
            'reserved' => '0.0000',
        ]);

        $mainBal = StockBalance::create([
            'organization_id' => $this->orgId,
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->locA->id,
            'goods_id' => $this->lotGoods->id,
            'on_hand' => '100.0000',
            'reserved' => '0.0000',
        ]);

        // 1. Create Issue Document (20 qty)
        $doc = $this->withHeaders($this->authHeaders('L-RES-01'))->postJson('/api/v1/stock/documents', [
            'document_type' => 'ISSUE',
            'source_warehouse_id' => $this->warehouse->id,
            'source_location_id' => $this->locA->id,
        ])->json('data.id');

        $lineId = $this->withHeaders($this->authHeaders('L-RES-02'))->postJson("/api/v1/stock/documents/{$doc}/lines", [
            'goods_id' => $this->lotGoods->id,
            'lot_id' => $lot->id,
            'quantity' => 20,
        ])->json('data.id');

        $this->withHeaders($this->authHeaders('L-RES-03'))->postJson("/api/v1/stock/documents/{$doc}/submit");

        // 2. Reserve Lot
        $resId = $this->withHeaders($this->authHeaders('L-RES-04'))
            ->postJson("/api/v1/stock/documents/{$doc}/lines/{$lineId}/reserve")
            ->json('data.id');

        // Check lot reserved and main reserved = 20
        $lotBal->refresh();
        $mainBal->refresh();
        $this->assertEquals('20.0000', $lotBal->reserved);
        $this->assertEquals('20.0000', $mainBal->reserved);

        // 3. Approve and POST -> Consume
        $this->withHeaders($this->authHeaders('L-RES-05'))->postJson("/api/v1/stock/documents/{$doc}/approve");

        $this->withHeaders($this->authHeaders('L-POST-01'))
            ->postJson("/api/v1/stock/documents/{$doc}/post")
            ->assertStatus(200);

        // Check lot on_hand = 80, reserved = 0
        $lotBal->refresh();
        $mainBal->refresh();
        $this->assertEquals('80.0000', $lotBal->on_hand);
        $this->assertEquals('0.0000', $lotBal->reserved);
        $this->assertEquals('80.0000', $mainBal->on_hand);
        $this->assertEquals('0.0000', $mainBal->reserved);

        // Check reservation status = CONSUMED
        $this->assertEquals(StockReservationStatus::CONSUMED, StockReservation::find($resId)->status);
    }

    public function test_cannot_reserve_expired_lot(): void
    {
        $pastExp = Carbon::now()->subDays(5)->toDateString();
        $lot = StockLot::create([
            'organization_id' => $this->orgId,
            'goods_id' => $this->lotGoods->id,
            'lot_no' => 'LOT-EXP-RES',
            'expired_at' => $pastExp,
        ]);

        StockLotBalance::create([
            'organization_id' => $this->orgId,
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->locA->id,
            'lot_id' => $lot->id,
            'on_hand' => '50.0000',
            'reserved' => '0.0000',
        ]);

        StockBalance::create([
            'organization_id' => $this->orgId,
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->locA->id,
            'goods_id' => $this->lotGoods->id,
            'on_hand' => '50.0000',
            'reserved' => '0.0000',
        ]);

        $doc = $this->withHeaders($this->authHeaders('L-EXP-01'))->postJson('/api/v1/stock/documents', [
            'document_type' => 'ISSUE',
            'source_warehouse_id' => $this->warehouse->id,
            'source_location_id' => $this->locA->id,
        ])->json('data.id');

        $lineId = $this->withHeaders($this->authHeaders('L-EXP-02'))->postJson("/api/v1/stock/documents/{$doc}/lines", [
            'goods_id' => $this->lotGoods->id,
            'lot_id' => $lot->id,
            'quantity' => 10,
        ])->json('data.id');

        $this->withHeaders($this->authHeaders('L-EXP-03'))->postJson("/api/v1/stock/documents/{$doc}/submit");

        // Attempt Reserve -> rejected with 409 LOT_EXPIRED
        $res = $this->withHeaders($this->authHeaders('L-EXP-04'))
            ->postJson("/api/v1/stock/documents/{$doc}/lines/{$lineId}/reserve");

        $res->assertStatus(409)
            ->assertJson([
                'success' => false,
                'code' => 'LOT_EXPIRED',
            ]);
    }
}
