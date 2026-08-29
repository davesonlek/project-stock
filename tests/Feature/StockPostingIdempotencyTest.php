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

class StockPostingIdempotencyTest extends TestCase
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

    protected function authHeaders(string $idempotencyKey = 'IDEM-KEY-001'): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'X-Organization-Id' => $this->orgId,
            'Idempotency-Key' => $idempotencyKey,
        ];
    }

    public function test_same_idempotency_key_replay_returns_cached_response_and_does_not_mutate_stock_again(): void
    {
        // 1. Initial Stock = 100
        $balance = StockBalance::updateOrCreate(
            ['organization_id' => $this->orgId, 'warehouse_id' => $this->warehouse->id, 'location_id' => $this->locA->id, 'goods_id' => $this->goods->id],
            ['on_hand' => '100.0000', 'reserved' => '0.0000']
        );

        // 2. Create and approve ISSUE 10
        $docRes = $this->withHeaders($this->authHeaders('IDEM-SETUP-01'))
            ->postJson('/api/v1/stock/documents', [
                'document_type' => 'ISSUE',
                'source_warehouse_id' => $this->warehouse->id,
                'source_location_id' => $this->locA->id,
            ]);
        $docId = $docRes->json('data.id');

        $this->withHeaders($this->authHeaders('IDEM-SETUP-02'))->postJson("/api/v1/stock/documents/{$docId}/lines", [
            'goods_id' => $this->goods->id,
            'quantity' => 10,
        ]);
        $this->withHeaders($this->authHeaders('IDEM-SETUP-03'))->postJson("/api/v1/stock/documents/{$docId}/submit");
        $this->withHeaders($this->authHeaders('IDEM-SETUP-04'))->postJson("/api/v1/stock/documents/{$docId}/approve");

        $fixedKey = 'IDEM-FIXED-KEY-999';

        // 3. First execution
        $res1 = $this->withHeaders($this->authHeaders($fixedKey))
            ->postJson("/api/v1/stock/documents/{$docId}/post");

        $res1->assertStatus(200);
        $this->assertEquals('POSTED', $res1->json('data.status'));

        // 4. Repeat 4 times with same key
        for ($i = 0; $i < 4; $i++) {
            $replayRes = $this->withHeaders($this->authHeaders($fixedKey))
                ->postJson("/api/v1/stock/documents/{$docId}/post");

            $replayRes->assertStatus(200);
            $this->assertEquals('POSTED', $replayRes->json('data.status'));
        }

        // 5. Verify stock decreased by 10 ONLY ONCE (100 -> 90)
        $balance->refresh();
        $this->assertEquals('90.0000', $balance->on_hand);

        // 6. Verify movement count is exactly 1
        $this->assertEquals(1, StockMovement::where('document_id', $docId)->count());
    }

    public function test_same_idempotency_key_with_different_document_returns_409_conflict(): void
    {
        $sharedKey = 'CONFLICT-KEY-777';

        // 1. Doc 1
        $docRes1 = $this->withHeaders($this->authHeaders('CONF-SETUP-01'))
            ->postJson('/api/v1/stock/documents', [
                'document_type' => 'RECEIVE',
                'destination_warehouse_id' => $this->warehouse->id,
                'destination_location_id' => $this->locA->id,
            ]);
        $docId1 = $docRes1->json('data.id');

        $this->withHeaders($this->authHeaders('CONF-SETUP-02'))->postJson("/api/v1/stock/documents/{$docId1}/lines", [
            'goods_id' => $this->goods->id,
            'quantity' => 10,
        ]);
        $this->withHeaders($this->authHeaders('CONF-SETUP-03'))->postJson("/api/v1/stock/documents/{$docId1}/submit");
        $this->withHeaders($this->authHeaders('CONF-SETUP-04'))->postJson("/api/v1/stock/documents/{$docId1}/approve");

        // Post Doc 1 with sharedKey
        $this->withHeaders($this->authHeaders($sharedKey))
            ->postJson("/api/v1/stock/documents/{$docId1}/post")
            ->assertStatus(200);

        // 2. Doc 2
        $docRes2 = $this->withHeaders($this->authHeaders('CONF-SETUP-05'))
            ->postJson('/api/v1/stock/documents', [
                'document_type' => 'RECEIVE',
                'destination_warehouse_id' => $this->warehouse->id,
                'destination_location_id' => $this->locA->id,
            ]);
        $docId2 = $docRes2->json('data.id');

        $this->withHeaders($this->authHeaders('CONF-SETUP-06'))->postJson("/api/v1/stock/documents/{$docId2}/lines", [
            'goods_id' => $this->goods->id,
            'quantity' => 20,
        ]);
        $this->withHeaders($this->authHeaders('CONF-SETUP-07'))->postJson("/api/v1/stock/documents/{$docId2}/submit");
        $this->withHeaders($this->authHeaders('CONF-SETUP-08'))->postJson("/api/v1/stock/documents/{$docId2}/approve");

        // Try to post Doc 2 with the SAME sharedKey
        $resConflict = $this->withHeaders($this->authHeaders($sharedKey))
            ->postJson("/api/v1/stock/documents/{$docId2}/post");

        $resConflict->assertStatus(409)
            ->assertJson([
                'success' => false,
                'code' => 'IDEMPOTENCY_KEY_CONFLICT',
            ]);
    }

    public function test_same_document_double_post_with_different_keys_rejects_second_request(): void
    {
        // 1. Create and approve RECEIVE
        $docRes = $this->withHeaders($this->authHeaders('DBL-SETUP-01'))
            ->postJson('/api/v1/stock/documents', [
                'document_type' => 'RECEIVE',
                'destination_warehouse_id' => $this->warehouse->id,
                'destination_location_id' => $this->locA->id,
            ]);
        $docId = $docRes->json('data.id');

        $this->withHeaders($this->authHeaders('DBL-SETUP-02'))->postJson("/api/v1/stock/documents/{$docId}/lines", [
            'goods_id' => $this->goods->id,
            'quantity' => 10,
        ]);
        $this->withHeaders($this->authHeaders('DBL-SETUP-03'))->postJson("/api/v1/stock/documents/{$docId}/submit");
        $this->withHeaders($this->authHeaders('DBL-SETUP-04'))->postJson("/api/v1/stock/documents/{$docId}/approve");

        // 2. Request A with Key A
        $resA = $this->withHeaders($this->authHeaders('KEY-ALPHA'))
            ->postJson("/api/v1/stock/documents/{$docId}/post");
        $resA->assertStatus(200);

        // 3. Request B with Key B
        $resB = $this->withHeaders($this->authHeaders('KEY-BETA'))
            ->postJson("/api/v1/stock/documents/{$docId}/post");

        $resB->assertStatus(409)
            ->assertJson([
                'success' => false,
                'code' => 'DOCUMENT_ALREADY_POSTED',
            ]);
    }
}
