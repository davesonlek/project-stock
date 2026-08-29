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

class TransferStockPostingTest extends TestCase
{
    use DatabaseTransactions;

    protected string $token;
    protected string $orgId;
    protected Warehouse $warehouse;
    protected WarehouseLocation $locA;
    protected WarehouseLocation $locB;
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
        $this->locB = WarehouseLocation::where('warehouse_id', $this->warehouse->id)->where('code', 'A-01-02')->first();
        $this->goods = Goods::where('organization_id', $this->orgId)->where('is_lot_tracked', false)->where('is_serial_tracked', false)->first();
    }

    protected function authHeaders(string $idempotencyKey = 'TRF-KEY-001'): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'X-Organization-Id' => $this->orgId,
            'Idempotency-Key' => $idempotencyKey,
        ];
    }

    public function test_transfer_updates_source_and_destination_atomically_and_conserves_total_stock(): void
    {
        // 1. Initial State: Loc A = 100, Loc B = 20
        $balA = StockBalance::updateOrCreate(
            ['organization_id' => $this->orgId, 'warehouse_id' => $this->warehouse->id, 'location_id' => $this->locA->id, 'goods_id' => $this->goods->id],
            ['on_hand' => '100.0000', 'reserved' => '0.0000']
        );
        $balB = StockBalance::updateOrCreate(
            ['organization_id' => $this->orgId, 'warehouse_id' => $this->warehouse->id, 'location_id' => $this->locB->id, 'goods_id' => $this->goods->id],
            ['on_hand' => '20.0000', 'reserved' => '0.0000']
        );

        $totalBefore = bcadd($balA->on_hand, $balB->on_hand, 4);
        $this->assertEquals('120.0000', $totalBefore);

        // 2. Create TRANSFER document for 30 qty (Loc A -> Loc B)
        $docRes = $this->withHeaders($this->authHeaders('TRF-DOC-01'))
            ->postJson('/api/v1/stock/documents', [
                'document_type' => 'TRANSFER',
                'source_warehouse_id' => $this->warehouse->id,
                'source_location_id' => $this->locA->id,
                'destination_warehouse_id' => $this->warehouse->id,
                'destination_location_id' => $this->locB->id,
            ]);
        $docId = $docRes->json('data.id');

        $this->withHeaders($this->authHeaders('TRF-DOC-02'))->postJson("/api/v1/stock/documents/{$docId}/lines", [
            'goods_id' => $this->goods->id,
            'quantity' => 30,
        ]);
        $this->withHeaders($this->authHeaders('TRF-DOC-03'))->postJson("/api/v1/stock/documents/{$docId}/submit");
        $this->withHeaders($this->authHeaders('TRF-DOC-04'))->postJson("/api/v1/stock/documents/{$docId}/approve");

        // 3. POST Transfer
        $postRes = $this->withHeaders($this->authHeaders('TRF-POST-01'))
            ->postJson("/api/v1/stock/documents/{$docId}/post");

        $postRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'POSTED',
                    'document_type' => 'TRANSFER',
                ],
            ]);

        // 4. Verify Final Balances
        $balA->refresh();
        $balB->refresh();

        $this->assertEquals('70.0000', $balA->on_hand);
        $this->assertEquals('50.0000', $balB->on_hand);

        // Conservation invariant
        $totalAfter = bcadd($balA->on_hand, $balB->on_hand, 4);
        $this->assertEquals('120.0000', $totalAfter);

        // 5. Verify Movements
        $movements = StockMovement::where('document_id', $docId)->get();
        $this->assertCount(2, $movements);

        $outMv = $movements->firstWhere('movement_type', \Modules\InventoryTransaction\Enums\StockMovementType::TRANSFER_OUT);
        $inMv = $movements->firstWhere('movement_type', \Modules\InventoryTransaction\Enums\StockMovementType::TRANSFER_IN);

        $this->assertNotNull($outMv);
        $this->assertNotNull($inMv);
        $this->assertEquals('-30.0000', $outMv->quantity_delta);
        $this->assertEquals($this->locA->id, $outMv->location_id);
        $this->assertEquals('30.0000', $inMv->quantity_delta);
        $this->assertEquals($this->locB->id, $inMv->location_id);
    }
}
