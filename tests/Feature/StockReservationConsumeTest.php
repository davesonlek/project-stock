<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\InventoryTransaction\Enums\StockReservationStatus;
use Modules\InventoryTransaction\Models\StockBalance;
use Modules\InventoryTransaction\Models\StockMovement;
use Modules\InventoryTransaction\Models\StockReservation;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Product;
use Modules\MasterData\Models\Unit;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;
use Tests\TestCase;

class StockReservationConsumeTest extends TestCase
{
    use DatabaseTransactions;

    protected string $token;
    protected string $orgId;
    protected Warehouse $warehouse;
    protected WarehouseLocation $locA;
    protected Goods $goodsA;
    protected Goods $goodsB;

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

        $this->goodsA = Goods::create([
            'organization_id' => $this->orgId,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'sku' => 'CSM-A-' . uniqid(),
            'name' => 'Consume Goods A',
            'pack_size' => 1,
            'is_lot_tracked' => false,
            'is_serial_tracked' => false,
            'is_active' => true,
        ]);

        $this->goodsB = Goods::create([
            'organization_id' => $this->orgId,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'sku' => 'CSM-B-' . uniqid(),
            'name' => 'Consume Goods B',
            'pack_size' => 1,
            'is_lot_tracked' => false,
            'is_serial_tracked' => false,
            'is_active' => true,
        ]);
    }

    protected function authHeaders(string $key = 'CONSUME-KEY-001'): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'X-Organization-Id' => $this->orgId,
            'Idempotency-Key' => $key,
        ];
    }

    public function test_post_reserved_issue_consumes_reservation_and_updates_balances(): void
    {
        // 1. Initial Stock: on_hand = 100, reserved = 0
        $balance = StockBalance::create([
            'organization_id' => $this->orgId,
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->locA->id,
            'goods_id' => $this->goodsA->id,
            'on_hand' => '100.0000',
            'reserved' => '0.0000',
        ]);

        // 2. Create Issue (20 qty) -> Submit -> Reserve -> Approve
        $doc = $this->withHeaders($this->authHeaders('CSM-01'))->postJson('/api/v1/stock/documents', [
            'document_type' => 'ISSUE',
            'source_warehouse_id' => $this->warehouse->id,
            'source_location_id' => $this->locA->id,
        ])->json('data.id');

        $lineId = $this->withHeaders($this->authHeaders('CSM-02'))->postJson("/api/v1/stock/documents/{$doc}/lines", [
            'goods_id' => $this->goodsA->id,
            'quantity' => 20,
        ])->json('data.id');

        $this->withHeaders($this->authHeaders('CSM-03'))->postJson("/api/v1/stock/documents/{$doc}/submit");

        $resId = $this->withHeaders($this->authHeaders('CSM-04'))
            ->postJson("/api/v1/stock/documents/{$doc}/lines/{$lineId}/reserve")
            ->json('data.id');

        $this->withHeaders($this->authHeaders('CSM-05'))->postJson("/api/v1/stock/documents/{$doc}/approve");

        // Verify state before post: on_hand = 100, reserved = 20
        $balance->refresh();
        $this->assertEquals('100.0000', $balance->on_hand);
        $this->assertEquals('20.0000', $balance->reserved);

        // 3. POST Document
        $postRes = $this->withHeaders($this->authHeaders('CSM-POST-01'))
            ->postJson("/api/v1/stock/documents/{$doc}/post");

        $postRes->assertStatus(200);

        // 4. Verify Final State: on_hand = 80, reserved = 0, available = 80
        $balance->refresh();
        $this->assertEquals('80.0000', $balance->on_hand);
        $this->assertEquals('0.0000', $balance->reserved);

        // 5. Verify Reservation status = CONSUMED
        $reservation = StockReservation::find($resId);
        $this->assertEquals(StockReservationStatus::CONSUMED, $reservation->status);
        $this->assertNotNull($reservation->consumed_by);
        $this->assertNotNull($reservation->consumed_at);

        // 6. Verify Movement recorded (-20)
        $movement = StockMovement::where('document_id', $doc)->first();
        $this->assertNotNull($movement);
        $this->assertEquals('-20.0000', $movement->quantity_delta);
    }

    public function test_mixed_reservation_document_posting(): void
    {
        // Goods A: on_hand = 50, Goods B: on_hand = 50
        $balA = StockBalance::create([
            'organization_id' => $this->orgId,
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->locA->id,
            'goods_id' => $this->goodsA->id,
            'on_hand' => '50.0000',
            'reserved' => '0.0000',
        ]);
        $balB = StockBalance::create([
            'organization_id' => $this->orgId,
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->locA->id,
            'goods_id' => $this->goodsB->id,
            'on_hand' => '50.0000',
            'reserved' => '0.0000',
        ]);

        $doc = $this->withHeaders($this->authHeaders('MIX-01'))->postJson('/api/v1/stock/documents', [
            'document_type' => 'ISSUE',
            'source_warehouse_id' => $this->warehouse->id,
            'source_location_id' => $this->locA->id,
        ])->json('data.id');

        $lineA = $this->withHeaders($this->authHeaders('MIX-02'))->postJson("/api/v1/stock/documents/{$doc}/lines", [
            'goods_id' => $this->goodsA->id,
            'quantity' => 15,
        ])->json('data.id');

        $lineB = $this->withHeaders($this->authHeaders('MIX-03'))->postJson("/api/v1/stock/documents/{$doc}/lines", [
            'goods_id' => $this->goodsB->id,
            'quantity' => 25,
        ])->json('data.id');

        $this->withHeaders($this->authHeaders('MIX-04'))->postJson("/api/v1/stock/documents/{$doc}/submit");

        // Reserve only Line A (15 qty)
        $resA = $this->withHeaders($this->authHeaders('MIX-05'))
            ->postJson("/api/v1/stock/documents/{$doc}/lines/{$lineA}/reserve")
            ->json('data.id');

        $this->withHeaders($this->authHeaders('MIX-06'))->postJson("/api/v1/stock/documents/{$doc}/approve");

        // POST Document
        $this->withHeaders($this->authHeaders('MIX-POST'))
            ->postJson("/api/v1/stock/documents/{$doc}/post")
            ->assertStatus(200);

        // Goods A: on_hand 50 -> 35, reserved 15 -> 0
        $balA->refresh();
        $this->assertEquals('35.0000', $balA->on_hand);
        $this->assertEquals('0.0000', $balA->reserved);

        // Goods B: on_hand 50 -> 25, reserved 0 -> 0
        $balB->refresh();
        $this->assertEquals('25.0000', $balB->on_hand);
        $this->assertEquals('0.0000', $balB->reserved);

        // Reservation A is CONSUMED
        $this->assertEquals(StockReservationStatus::CONSUMED, StockReservation::find($resA)->status);
    }

    public function test_reservation_blocks_subsequent_unreserved_issue(): void
    {
        // on_hand = 100, reserved = 80 -> available = 20
        StockBalance::create([
            'organization_id' => $this->orgId,
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->locA->id,
            'goods_id' => $this->goodsA->id,
            'on_hand' => '100.0000',
            'reserved' => '80.0000',
        ]);

        // Create independent Issue for 30 qty (unreserved)
        $doc = $this->withHeaders($this->authHeaders('BLK-01'))->postJson('/api/v1/stock/documents', [
            'document_type' => 'ISSUE',
            'source_warehouse_id' => $this->warehouse->id,
            'source_location_id' => $this->locA->id,
        ])->json('data.id');

        $this->withHeaders($this->authHeaders('BLK-02'))->postJson("/api/v1/stock/documents/{$doc}/lines", [
            'goods_id' => $this->goodsA->id,
            'quantity' => 30,
        ]);

        $this->withHeaders($this->authHeaders('BLK-03'))->postJson("/api/v1/stock/documents/{$doc}/submit");
        $this->withHeaders($this->authHeaders('BLK-04'))->postJson("/api/v1/stock/documents/{$doc}/approve");

        // Attempt POST -> should fail with 409 INSUFFICIENT_AVAILABLE_STOCK
        $this->withHeaders($this->authHeaders('BLK-POST'))
            ->postJson("/api/v1/stock/documents/{$doc}/post")
            ->assertStatus(409)
            ->assertJson([
                'success' => false,
                'code' => 'INSUFFICIENT_AVAILABLE_STOCK',
            ]);
    }
}
