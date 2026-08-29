<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\InventoryTransaction\Models\StockBalance;
use Modules\InventoryTransaction\Models\StockDocument;
use Modules\InventoryTransaction\Models\StockMovement;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Supplier;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;
use Tests\TestCase;

class ReceiveStockPostingTest extends TestCase
{
    use DatabaseTransactions;

    protected string $token;
    protected string $staffToken;
    protected string $orgId;
    protected Warehouse $warehouse;
    protected WarehouseLocation $locA;
    protected Supplier $supplier;
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

        $staffLogin = $this->postJson('/api/v1/auth/login', [
            'email' => 'staff@test.com',
            'password' => 'password123',
        ]);
        $this->staffToken = $staffLogin->json('data.access_token');

        $this->warehouse = Warehouse::where('organization_id', $this->orgId)->where('code', 'MAIN')->first();
        $this->locA = WarehouseLocation::where('warehouse_id', $this->warehouse->id)->where('code', 'A-01-01')->first();
        $this->supplier = Supplier::where('organization_id', $this->orgId)->first();
        $this->goods = Goods::where('organization_id', $this->orgId)->where('is_lot_tracked', false)->where('is_serial_tracked', false)->first();
    }

    protected function authHeaders(string $idempotencyKey = 'RECEIVE-KEY-001', ?string $token = null): array
    {
        return [
            'Authorization' => 'Bearer ' . ($token ?? $this->token),
            'X-Organization-Id' => $this->orgId,
            'Idempotency-Key' => $idempotencyKey,
        ];
    }

    public function test_first_receive_creates_stock_balance_row_and_records_movement(): void
    {
        // Ensure no stock balance exists for this location and goods
        StockBalance::where('organization_id', $this->orgId)
            ->where('warehouse_id', $this->warehouse->id)
            ->where('location_id', $this->locA->id)
            ->where('goods_id', $this->goods->id)
            ->delete();

        // 1. Create RECEIVE document
        $res = $this->withHeaders($this->authHeaders('FIRST-REC-01'))
            ->postJson('/api/v1/stock/documents', [
                'document_type' => 'RECEIVE',
                'destination_warehouse_id' => $this->warehouse->id,
                'destination_location_id' => $this->locA->id,
                'supplier_id' => $this->supplier->id,
            ]);
        $docId = $res->json('data.id');

        // 2. Add line 100 qty
        $this->withHeaders($this->authHeaders('FIRST-REC-02'))
            ->postJson("/api/v1/stock/documents/{$docId}/lines", [
                'goods_id' => $this->goods->id,
                'quantity' => 100,
            ])->assertStatus(201);

        // 3. Submit & Approve
        $this->withHeaders($this->authHeaders('FIRST-REC-03'))->postJson("/api/v1/stock/documents/{$docId}/submit")->assertStatus(200);
        $this->withHeaders($this->authHeaders('FIRST-REC-04'))->postJson("/api/v1/stock/documents/{$docId}/approve")->assertStatus(200);

        // 4. POST Document
        $postRes = $this->withHeaders($this->authHeaders('POST-REC-FIRST'))
            ->postJson("/api/v1/stock/documents/{$docId}/post");

        $postRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'POSTED',
                    'document_type' => 'RECEIVE',
                ],
            ]);

        // 5. Verify Stock Balance created and on_hand = 100
        $balance = StockBalance::where('organization_id', $this->orgId)
            ->where('warehouse_id', $this->warehouse->id)
            ->where('location_id', $this->locA->id)
            ->where('goods_id', $this->goods->id)
            ->first();

        $this->assertNotNull($balance);
        $this->assertEquals('100.0000', $balance->on_hand);
        $this->assertEquals('0.0000', $balance->reserved);

        // 6. Verify Movement recorded
        $movement = StockMovement::where('document_id', $docId)->first();
        $this->assertNotNull($movement);
        $this->assertEquals('RECEIVE', $movement->movement_type->value);
        $this->assertEquals('100.0000', $movement->quantity_delta);
        $this->assertEquals($this->warehouse->id, $movement->warehouse_id);
        $this->assertEquals($this->locA->id, $movement->location_id);
    }

    public function test_standard_receive_adds_to_existing_stock_balance(): void
    {
        // Seed initial balance 100
        $balance = StockBalance::updateOrCreate(
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

        // Create, line (50), submit, approve
        $docRes = $this->withHeaders($this->authHeaders('STD-REC-01'))
            ->postJson('/api/v1/stock/documents', [
                'document_type' => 'RECEIVE',
                'destination_warehouse_id' => $this->warehouse->id,
                'destination_location_id' => $this->locA->id,
            ]);
        $docId = $docRes->json('data.id');

        $this->withHeaders($this->authHeaders('STD-REC-02'))
            ->postJson("/api/v1/stock/documents/{$docId}/lines", [
                'goods_id' => $this->goods->id,
                'quantity' => 50,
            ]);

        $this->withHeaders($this->authHeaders('STD-REC-03'))->postJson("/api/v1/stock/documents/{$docId}/submit");
        $this->withHeaders($this->authHeaders('STD-REC-04'))->postJson("/api/v1/stock/documents/{$docId}/approve");

        // POST
        $this->withHeaders($this->authHeaders('STD-REC-05'))
            ->postJson("/api/v1/stock/documents/{$docId}/post")
            ->assertStatus(200);

        // Verify balance = 150
        $balance->refresh();
        $this->assertEquals('150.0000', $balance->on_hand);
    }

    public function test_post_requires_idempotency_key_header(): void
    {
        $docRes = $this->withHeaders($this->authHeaders('NO-IDEM-01'))
            ->postJson('/api/v1/stock/documents', [
                'document_type' => 'RECEIVE',
                'destination_warehouse_id' => $this->warehouse->id,
                'destination_location_id' => $this->locA->id,
            ]);
        $docId = $docRes->json('data.id');

        $this->withHeaders($this->authHeaders('NO-IDEM-02'))->postJson("/api/v1/stock/documents/{$docId}/lines", [
            'goods_id' => $this->goods->id,
            'quantity' => 10,
        ]);
        $this->withHeaders($this->authHeaders('NO-IDEM-03'))->postJson("/api/v1/stock/documents/{$docId}/submit");
        $this->withHeaders($this->authHeaders('NO-IDEM-04'))->postJson("/api/v1/stock/documents/{$docId}/approve");

        // Send post request WITHOUT Idempotency-Key
        $this->flushHeaders();
        $headersWithoutIdem = [
            'Authorization' => 'Bearer ' . $this->token,
            'X-Organization-Id' => $this->orgId,
        ];

        $response = $this->withHeaders($headersWithoutIdem)
            ->postJson("/api/v1/stock/documents/{$docId}/post");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'IDEMPOTENCY_KEY_REQUIRED',
            ]);
    }

    public function test_staff_cannot_post_stock_document(): void
    {
        $docRes = $this->withHeaders($this->authHeaders('STAFF-01'))
            ->postJson('/api/v1/stock/documents', [
                'document_type' => 'RECEIVE',
                'destination_warehouse_id' => $this->warehouse->id,
                'destination_location_id' => $this->locA->id,
            ]);
        $docId = $docRes->json('data.id');

        $this->withHeaders($this->authHeaders('STAFF-02'))->postJson("/api/v1/stock/documents/{$docId}/lines", [
            'goods_id' => $this->goods->id,
            'quantity' => 10,
        ]);
        $this->withHeaders($this->authHeaders('STAFF-03'))->postJson("/api/v1/stock/documents/{$docId}/submit");
        $this->withHeaders($this->authHeaders('STAFF-04'))->postJson("/api/v1/stock/documents/{$docId}/approve");

        // Staff tries to post -> 403 Forbidden
        $response = $this->withHeaders($this->authHeaders('STAFF-POST', $this->staffToken))
            ->postJson("/api/v1/stock/documents/{$docId}/post");

        $response->assertStatus(403);
    }

    public function test_cannot_post_non_approved_document(): void
    {
        $docRes = $this->withHeaders($this->authHeaders('DRAFT-01'))
            ->postJson('/api/v1/stock/documents', [
                'document_type' => 'RECEIVE',
                'destination_warehouse_id' => $this->warehouse->id,
                'destination_location_id' => $this->locA->id,
            ]);
        $docId = $docRes->json('data.id');

        $this->withHeaders($this->authHeaders('DRAFT-02'))->postJson("/api/v1/stock/documents/{$docId}/lines", [
            'goods_id' => $this->goods->id,
            'quantity' => 10,
        ]);

        // Attempt to POST while still in DRAFT
        $response = $this->withHeaders($this->authHeaders('POST-DRAFT'))
            ->postJson("/api/v1/stock/documents/{$docId}/post");

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'code' => 'INVALID_DOCUMENT_STATE',
            ]);
    }
}
