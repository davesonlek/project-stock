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

class IssueStockPostingTest extends TestCase
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

    protected function authHeaders(string $idempotencyKey = 'ISSUE-KEY-001'): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'X-Organization-Id' => $this->orgId,
            'Idempotency-Key' => $idempotencyKey,
        ];
    }

    public function test_standard_issue_decrements_stock_and_records_negative_movement(): void
    {
        // 1. Setup initial stock = 100
        StockBalance::updateOrCreate(
            [
                'organization_id' => $this->orgId,
                'warehouse_id' => $this->warehouse->id,
                'location_id' => $this->locA->id,
                'goods_id' => $this->goods->id,
            ],
            [
                'on_hand' => '100.0000',
                'reserved' => '0.0000',
            ]
        );

        // 2. Create ISSUE document for 30 qty
        $docRes = $this->withHeaders($this->authHeaders('ISSUE-STD-01'))
            ->postJson('/api/v1/stock/documents', [
                'document_type' => 'ISSUE',
                'source_warehouse_id' => $this->warehouse->id,
                'source_location_id' => $this->locA->id,
            ]);
        $docId = $docRes->json('data.id');

        $this->withHeaders($this->authHeaders('ISSUE-STD-02'))->postJson("/api/v1/stock/documents/{$docId}/lines", [
            'goods_id' => $this->goods->id,
            'quantity' => 30,
        ]);
        $this->withHeaders($this->authHeaders('ISSUE-STD-03'))->postJson("/api/v1/stock/documents/{$docId}/submit");
        $this->withHeaders($this->authHeaders('ISSUE-STD-04'))->postJson("/api/v1/stock/documents/{$docId}/approve");

        // 3. POST Document
        $postRes = $this->withHeaders($this->authHeaders('ISSUE-POST-01'))
            ->postJson("/api/v1/stock/documents/{$docId}/post");

        $postRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'POSTED',
                    'document_type' => 'ISSUE',
                ],
            ]);

        // 4. Verify Stock balance on_hand = 70
        $balance = StockBalance::where('organization_id', $this->orgId)
            ->where('warehouse_id', $this->warehouse->id)
            ->where('location_id', $this->locA->id)
            ->where('goods_id', $this->goods->id)
            ->first();

        $this->assertEquals('70.0000', $balance->on_hand);

        // 5. Verify Movement
        $movement = StockMovement::where('document_id', $docId)->first();
        $this->assertNotNull($movement);
        $this->assertEquals('ISSUE', $movement->movement_type->value);
        $this->assertEquals('-30.0000', $movement->quantity_delta);
    }

    public function test_issue_rejected_when_available_stock_insufficient_due_to_reservation(): void
    {
        // Setup on_hand = 100, reserved = 80 (available = 20)
        $balance = StockBalance::updateOrCreate(
            [
                'organization_id' => $this->orgId,
                'warehouse_id' => $this->warehouse->id,
                'location_id' => $this->locA->id,
                'goods_id' => $this->goods->id,
            ],
            [
                'on_hand' => '100.0000',
                'reserved' => '80.0000',
            ]
        );

        // Create ISSUE for 30 (exceeds available 20)
        $docRes = $this->withHeaders($this->authHeaders('RES-ISSUE-01'))
            ->postJson('/api/v1/stock/documents', [
                'document_type' => 'ISSUE',
                'source_warehouse_id' => $this->warehouse->id,
                'source_location_id' => $this->locA->id,
            ]);
        $docId = $docRes->json('data.id');

        $this->withHeaders($this->authHeaders('RES-ISSUE-02'))->postJson("/api/v1/stock/documents/{$docId}/lines", [
            'goods_id' => $this->goods->id,
            'quantity' => 30,
        ]);
        $this->withHeaders($this->authHeaders('RES-ISSUE-03'))->postJson("/api/v1/stock/documents/{$docId}/submit");
        $this->withHeaders($this->authHeaders('RES-ISSUE-04'))->postJson("/api/v1/stock/documents/{$docId}/approve");

        // POST should be rejected
        $postRes = $this->withHeaders($this->authHeaders('RES-ISSUE-POST'))
            ->postJson("/api/v1/stock/documents/{$docId}/post");

        $postRes->assertStatus(409)
            ->assertJson([
                'success' => false,
                'code' => 'INSUFFICIENT_AVAILABLE_STOCK',
            ]);

        // Stock unchanged
        $balance->refresh();
        $this->assertEquals('100.0000', $balance->on_hand);
        $this->assertEquals('80.0000', $balance->reserved);
    }
}
