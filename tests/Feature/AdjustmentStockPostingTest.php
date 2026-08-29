<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\InventoryTransaction\Models\StockBalance;
use Modules\InventoryTransaction\Models\StockMovement;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;
use Tests\TestCase;

class AdjustmentStockPostingTest extends TestCase
{
    use DatabaseTransactions;

    protected string $token;
    protected string $orgId;
    protected Warehouse $warehouse;
    protected WarehouseLocation $locA;
    protected Goods $goods;

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
        $this->goods = Goods::where('organization_id', $this->orgId)->where('is_lot_tracked', false)->where('is_serial_tracked', false)->first();
    }

    protected function authHeaders(string $idempotencyKey = 'ADJ-KEY-001'): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'X-Organization-Id' => $this->orgId,
            'Idempotency-Key' => $idempotencyKey,
        ];
    }

    public function test_adjustment_decreases_stock_and_creates_adjust_out_movement(): void
    {
        // 1. Current = 100
        $balance = StockBalance::updateOrCreate(
            ['organization_id' => $this->orgId, 'warehouse_id' => $this->warehouse->id, 'location_id' => $this->locA->id, 'goods_id' => $this->goods->id],
            ['on_hand' => '100.0000', 'reserved' => '0.0000']
        );

        // 2. Counted = 80 (delta = -20)
        $docRes = $this->withHeaders($this->authHeaders('ADJ-OUT-01'))
            ->postJson('/api/v1/stock/documents', [
                'document_type' => 'ADJUSTMENT',
                'source_warehouse_id' => $this->warehouse->id,
                'source_location_id' => $this->locA->id,
            ]);
        $docId = $docRes->json('data.id');

        $this->withHeaders($this->authHeaders('ADJ-OUT-02'))->postJson("/api/v1/stock/documents/{$docId}/lines", [
            'goods_id' => $this->goods->id,
            'counted_quantity' => 80,
        ]);
        $this->withHeaders($this->authHeaders('ADJ-OUT-03'))->postJson("/api/v1/stock/documents/{$docId}/submit");
        $this->withHeaders($this->authHeaders('ADJ-OUT-04'))->postJson("/api/v1/stock/documents/{$docId}/approve");

        // 3. POST
        $this->withHeaders($this->authHeaders('ADJ-OUT-POST'))
            ->postJson("/api/v1/stock/documents/{$docId}/post")
            ->assertStatus(200);

        // 4. Verify on_hand = 80 and Movement = -20
        $balance->refresh();
        $this->assertEquals('80.0000', $balance->on_hand);

        $movement = StockMovement::where('document_id', $docId)->first();
        $this->assertNotNull($movement);
        $this->assertEquals('ADJUST_OUT', $movement->movement_type->value);
        $this->assertEquals('-20.0000', $movement->quantity_delta);
    }

    public function test_adjustment_increases_stock_and_creates_adjust_in_movement(): void
    {
        // 1. Current = 100
        $balance = StockBalance::updateOrCreate(
            ['organization_id' => $this->orgId, 'warehouse_id' => $this->warehouse->id, 'location_id' => $this->locA->id, 'goods_id' => $this->goods->id],
            ['on_hand' => '100.0000', 'reserved' => '0.0000']
        );

        // 2. Counted = 120 (delta = +20)
        $docRes = $this->withHeaders($this->authHeaders('ADJ-IN-01'))
            ->postJson('/api/v1/stock/documents', [
                'document_type' => 'ADJUSTMENT',
                'source_warehouse_id' => $this->warehouse->id,
                'source_location_id' => $this->locA->id,
            ]);
        $docId = $docRes->json('data.id');

        $this->withHeaders($this->authHeaders('ADJ-IN-02'))->postJson("/api/v1/stock/documents/{$docId}/lines", [
            'goods_id' => $this->goods->id,
            'counted_quantity' => 120,
        ]);
        $this->withHeaders($this->authHeaders('ADJ-IN-03'))->postJson("/api/v1/stock/documents/{$docId}/submit");
        $this->withHeaders($this->authHeaders('ADJ-IN-04'))->postJson("/api/v1/stock/documents/{$docId}/approve");

        // 3. POST
        $this->withHeaders($this->authHeaders('ADJ-IN-POST'))
            ->postJson("/api/v1/stock/documents/{$docId}/post")
            ->assertStatus(200);

        // 4. Verify on_hand = 120 and Movement = +20
        $balance->refresh();
        $this->assertEquals('120.0000', $balance->on_hand);

        $movement = StockMovement::where('document_id', $docId)->first();
        $this->assertNotNull($movement);
        $this->assertEquals('ADJUST_IN', $movement->movement_type->value);
        $this->assertEquals('20.0000', $movement->quantity_delta);
    }

    public function test_adjustment_to_zero(): void
    {
        // 1. Current = 10
        $balance = StockBalance::updateOrCreate(
            ['organization_id' => $this->orgId, 'warehouse_id' => $this->warehouse->id, 'location_id' => $this->locA->id, 'goods_id' => $this->goods->id],
            ['on_hand' => '10.0000', 'reserved' => '0.0000']
        );

        // 2. Counted = 0
        $docRes = $this->withHeaders($this->authHeaders('ADJ-ZERO-01'))
            ->postJson('/api/v1/stock/documents', [
                'document_type' => 'ADJUSTMENT',
                'source_warehouse_id' => $this->warehouse->id,
                'source_location_id' => $this->locA->id,
            ]);
        $docId = $docRes->json('data.id');

        $this->withHeaders($this->authHeaders('ADJ-ZERO-02'))->postJson("/api/v1/stock/documents/{$docId}/lines", [
            'goods_id' => $this->goods->id,
            'counted_quantity' => 0,
        ]);
        $this->withHeaders($this->authHeaders('ADJ-ZERO-03'))->postJson("/api/v1/stock/documents/{$docId}/submit");
        $this->withHeaders($this->authHeaders('ADJ-ZERO-04'))->postJson("/api/v1/stock/documents/{$docId}/approve");

        // 3. POST
        $this->withHeaders($this->authHeaders('ADJ-ZERO-POST'))
            ->postJson("/api/v1/stock/documents/{$docId}/post")
            ->assertStatus(200);

        // 4. Verify on_hand = 0 and Movement = -10
        $balance->refresh();
        $this->assertEquals('0.0000', $balance->on_hand);

        $movement = StockMovement::where('document_id', $docId)->first();
        $this->assertEquals('-10.0000', $movement->quantity_delta);
    }

    public function test_zero_difference_adjustment_posts_successfully_without_movement(): void
    {
        // 1. Current = 100
        $balance = StockBalance::updateOrCreate(
            ['organization_id' => $this->orgId, 'warehouse_id' => $this->warehouse->id, 'location_id' => $this->locA->id, 'goods_id' => $this->goods->id],
            ['on_hand' => '100.0000', 'reserved' => '0.0000']
        );

        // 2. Counted = 100 (delta = 0)
        $docRes = $this->withHeaders($this->authHeaders('ADJ-NODIFF-01'))
            ->postJson('/api/v1/stock/documents', [
                'document_type' => 'ADJUSTMENT',
                'source_warehouse_id' => $this->warehouse->id,
                'source_location_id' => $this->locA->id,
            ]);
        $docId = $docRes->json('data.id');

        $this->withHeaders($this->authHeaders('ADJ-NODIFF-02'))->postJson("/api/v1/stock/documents/{$docId}/lines", [
            'goods_id' => $this->goods->id,
            'counted_quantity' => 100,
        ]);
        $this->withHeaders($this->authHeaders('ADJ-NODIFF-03'))->postJson("/api/v1/stock/documents/{$docId}/submit");
        $this->withHeaders($this->authHeaders('ADJ-NODIFF-04'))->postJson("/api/v1/stock/documents/{$docId}/approve");

        // 3. POST
        $postRes = $this->withHeaders($this->authHeaders('ADJ-NODIFF-POST'))
            ->postJson("/api/v1/stock/documents/{$docId}/post");

        $postRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'POSTED',
                ],
            ]);

        // 4. Verify Balance unchanged and NO movement created
        $balance->refresh();
        $this->assertEquals('100.0000', $balance->on_hand);

        $movementCount = StockMovement::where('document_id', $docId)->count();
        $this->assertEquals(0, $movementCount);
    }

    public function test_adjustment_rejected_when_counted_below_reserved_stock(): void
    {
        // 1. Current = 100, Reserved = 30
        StockBalance::updateOrCreate(
            ['organization_id' => $this->orgId, 'warehouse_id' => $this->warehouse->id, 'location_id' => $this->locA->id, 'goods_id' => $this->goods->id],
            ['on_hand' => '100.0000', 'reserved' => '30.0000']
        );

        // 2. Counted = 20 (below reserved 30)
        $docRes = $this->withHeaders($this->authHeaders('ADJ-RESERR-01'))
            ->postJson('/api/v1/stock/documents', [
                'document_type' => 'ADJUSTMENT',
                'source_warehouse_id' => $this->warehouse->id,
                'source_location_id' => $this->locA->id,
            ]);
        $docId = $docRes->json('data.id');

        $this->withHeaders($this->authHeaders('ADJ-RESERR-02'))->postJson("/api/v1/stock/documents/{$docId}/lines", [
            'goods_id' => $this->goods->id,
            'counted_quantity' => 20,
        ]);
        $this->withHeaders($this->authHeaders('ADJ-RESERR-03'))->postJson("/api/v1/stock/documents/{$docId}/submit");
        $this->withHeaders($this->authHeaders('ADJ-RESERR-04'))->postJson("/api/v1/stock/documents/{$docId}/approve");

        // 3. POST should be rejected
        $postRes = $this->withHeaders($this->authHeaders('ADJ-RESERR-POST'))
            ->postJson("/api/v1/stock/documents/{$docId}/post");

        $postRes->assertStatus(409)
            ->assertJson([
                'success' => false,
                'code' => 'ADJUSTMENT_BELOW_RESERVED_STOCK',
            ]);
    }
}
