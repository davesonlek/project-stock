<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\InventoryTransaction\Models\StockBalance;
use Modules\InventoryTransaction\Models\StockMovement;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Supplier;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;
use Tests\TestCase;

class StockDocumentWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    protected string $token;
    protected string $orgId;
    protected Warehouse $warehouse;
    protected WarehouseLocation $locA;
    protected WarehouseLocation $locB;
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

        $this->warehouse = Warehouse::where('organization_id', $this->orgId)->where('code', 'MAIN')->first();
        $this->locA = WarehouseLocation::where('warehouse_id', $this->warehouse->id)->where('code', 'A-01-01')->first();
        $this->locB = WarehouseLocation::where('warehouse_id', $this->warehouse->id)->where('code', 'A-01-02')->first();
        $this->supplier = Supplier::where('organization_id', $this->orgId)->first();
        $this->goods = Goods::where('organization_id', $this->orgId)->where('is_lot_tracked', false)->where('is_serial_tracked', false)->first();
    }

    protected function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'X-Organization-Id' => $this->orgId,
        ];
    }

    public function test_receive_document_workflow_and_guarantees_no_stock_balance_or_movement_change(): void
    {
        $balanceBefore = StockBalance::count();
        $movementsBefore = StockMovement::count();

        // 1. Create RECEIVE document
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/stock/documents', [
                'document_type' => 'RECEIVE',
                'destination_warehouse_id' => $this->warehouse->id,
                'destination_location_id' => $this->locA->id,
                'supplier_id' => $this->supplier->id,
                'remarks' => 'Inbound Delivery #101',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'document_type' => 'RECEIVE',
                    'status' => 'DRAFT',
                ],
            ]);

        $docId = $response->json('data.id');

        // 2. Add Line
        $lineRes = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/stock/documents/{$docId}/lines", [
                'goods_id' => $this->goods->id,
                'quantity' => 50,
                'unit_cost' => 15.00,
            ]);

        $lineRes->assertStatus(201);

        // 3. Submit Document
        $submitRes = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/stock/documents/{$docId}/submit");

        $submitRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'PENDING',
                ],
            ]);

        // 4. Approve Document
        $approveRes = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/stock/documents/{$docId}/approve");

        $approveRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'APPROVED',
                ],
            ]);

        // 5. Verify NO stock balance or stock movement changes
        $this->assertEquals($balanceBefore, StockBalance::count());
        $this->assertEquals($movementsBefore, StockMovement::count());
    }

    public function test_issue_document_validation_and_approval(): void
    {
        // 1. Create ISSUE
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/stock/documents', [
                'document_type' => 'ISSUE',
                'source_warehouse_id' => $this->warehouse->id,
                'source_location_id' => $this->locA->id,
                'remarks' => 'Sales Outbound',
            ]);

        $response->assertStatus(201);
        $docId = $response->json('data.id');

        // 2. Add Line
        $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/stock/documents/{$docId}/lines", [
                'goods_id' => $this->goods->id,
                'quantity' => 10,
            ])->assertStatus(201);

        // 3. Submit & Approve
        $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/stock/documents/{$docId}/submit")
            ->assertStatus(200)
            ->assertJson(['data' => ['status' => 'PENDING']]);

        $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/stock/documents/{$docId}/approve")
            ->assertStatus(200)
            ->assertJson(['data' => ['status' => 'APPROVED']]);
    }

    public function test_transfer_document_rejects_same_source_and_destination_location(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/stock/documents', [
                'document_type' => 'TRANSFER',
                'source_warehouse_id' => $this->warehouse->id,
                'source_location_id' => $this->locA->id,
                'destination_warehouse_id' => $this->warehouse->id,
                'destination_location_id' => $this->locA->id, // SAME!
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'SAME_TRANSFER_LOCATION',
            ]);
    }

    public function test_adjustment_document_supports_counted_quantity_zero(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/stock/documents', [
                'document_type' => 'ADJUSTMENT',
                'source_warehouse_id' => $this->warehouse->id,
                'source_location_id' => $this->locA->id,
            ]);

        $response->assertStatus(201);
        $docId = $response->json('data.id');

        // Add line with counted_quantity = 0
        $lineRes = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/stock/documents/{$docId}/lines", [
                'goods_id' => $this->goods->id,
                'counted_quantity' => 0,
            ]);

        $lineRes->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'counted_quantity' => 0,
                    'quantity' => null,
                ],
            ]);

        // Submit & Approve
        $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/stock/documents/{$docId}/submit")
            ->assertStatus(200);

        $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/stock/documents/{$docId}/approve")
            ->assertStatus(200);
    }
}
