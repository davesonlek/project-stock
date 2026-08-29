<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\InventoryTransaction\Enums\StockDocumentStatus;
use Modules\InventoryTransaction\Enums\StockMovementType;
use Modules\InventoryTransaction\Models\StockBalance;
use Modules\InventoryTransaction\Models\StockDocument;
use Modules\InventoryTransaction\Models\StockMovement;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Product;
use Modules\MasterData\Models\Unit;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;
use Tests\TestCase;

class ReceiveReversalTest extends TestCase
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
            'email' => 'admin@test.com',
            'password' => 'password123',
        ]);
        $this->token = $login->json('data.access_token');

        $this->warehouse = Warehouse::where('organization_id', $this->orgId)->where('code', 'MAIN')->first();
        $this->locA = WarehouseLocation::where('warehouse_id', $this->warehouse->id)->where('code', 'A-01-01')->first();

        $product = Product::where('organization_id', $this->orgId)->first();
        $unit = Unit::where('organization_id', $this->orgId)->first();

        $this->goods = Goods::create([
            'organization_id' => $this->orgId,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'sku' => 'REV-REC-' . uniqid(),
            'name' => 'Rev Rec Goods',
            'pack_size' => 1,
            'is_lot_tracked' => false,
            'is_serial_tracked' => false,
            'is_active' => true,
        ]);
    }

    protected function authHeaders(string $key = 'REV-KEY-001'): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'X-Organization-Id' => $this->orgId,
            'Idempotency-Key' => $key,
        ];
    }

    public function test_standard_receive_reversal_decrements_stock_and_creates_compensating_movement(): void
    {
        // 1. Create and Post Receive of 100
        $doc = $this->withHeaders($this->authHeaders('RCV-01'))->postJson('/api/v1/stock/documents', [
            'document_type' => 'RECEIVE',
            'destination_warehouse_id' => $this->warehouse->id,
            'destination_location_id' => $this->locA->id,
        ])->json('data.id');

        $this->withHeaders($this->authHeaders('RCV-02'))->postJson("/api/v1/stock/documents/{$doc}/lines", [
            'goods_id' => $this->goods->id,
            'quantity' => 100,
        ]);
        $this->withHeaders($this->authHeaders('RCV-03'))->postJson("/api/v1/stock/documents/{$doc}/submit");
        $this->withHeaders($this->authHeaders('RCV-04'))->postJson("/api/v1/stock/documents/{$doc}/approve");
        $this->withHeaders($this->authHeaders('RCV-POST'))->postJson("/api/v1/stock/documents/{$doc}/post");

        // Verify state after post: on_hand = 100
        $balance = StockBalance::where('goods_id', $this->goods->id)->where('location_id', $this->locA->id)->first();
        $this->assertEquals('100.0000', $balance->on_hand);

        $origMovement = StockMovement::where('document_id', $doc)->first();
        $this->assertEquals('100.0000', $origMovement->quantity_delta);

        // 2. Reverse Document
        $revRes = $this->withHeaders($this->authHeaders('REV-POST-01'))
            ->postJson("/api/v1/stock/documents/{$doc}/reverse", [
                'reason' => 'Vendor delivered wrong items',
            ]);

        $revRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'document_type' => 'REVERSAL',
                    'status' => 'POSTED',
                    'reversal_of' => $doc,
                ],
            ]);

        $revDocId = $revRes->json('data.id');

        // 3. Verify Database State
        // Balance returned to 0
        $balance->refresh();
        $this->assertEquals('0.0000', $balance->on_hand);

        // Original Document is REVERSED
        $origDoc = StockDocument::find($doc);
        $this->assertEquals(StockDocumentStatus::REVERSED, $origDoc->status);

        // Compensating Movement inserted: -100, reversal_of = origMovement.id
        $compMovement = StockMovement::where('document_id', $revDocId)->first();
        $this->assertNotNull($compMovement);
        $this->assertEquals(StockMovementType::REVERSAL, $compMovement->movement_type);
        $this->assertEquals('-100.0000', $compMovement->quantity_delta);
        $this->assertEquals($origMovement->id, $compMovement->reversal_of);

        // Original movement remains UNTOUCHED
        $origMovement->refresh();
        $this->assertEquals('100.0000', $origMovement->quantity_delta);
        $this->assertEquals(StockMovementType::RECEIVE, $origMovement->movement_type);
    }

    public function test_receive_reversal_is_rejected_when_stock_has_been_consumed(): void
    {
        // 1. Receive 100
        $recDoc = $this->withHeaders($this->authHeaders('RCV-A01'))->postJson('/api/v1/stock/documents', [
            'document_type' => 'RECEIVE',
            'destination_warehouse_id' => $this->warehouse->id,
            'destination_location_id' => $this->locA->id,
        ])->json('data.id');
        $this->withHeaders($this->authHeaders('RCV-A02'))->postJson("/api/v1/stock/documents/{$recDoc}/lines", ['goods_id' => $this->goods->id, 'quantity' => 100]);
        $this->withHeaders($this->authHeaders('RCV-A03'))->postJson("/api/v1/stock/documents/{$recDoc}/submit");
        $this->withHeaders($this->authHeaders('RCV-A04'))->postJson("/api/v1/stock/documents/{$recDoc}/approve");
        $this->withHeaders($this->authHeaders('RCV-APOST'))->postJson("/api/v1/stock/documents/{$recDoc}/post");

        // 2. Issue 90 (Current on_hand becomes 10)
        $issDoc = $this->withHeaders($this->authHeaders('ISS-A01'))->postJson('/api/v1/stock/documents', [
            'document_type' => 'ISSUE',
            'source_warehouse_id' => $this->warehouse->id,
            'source_location_id' => $this->locA->id,
        ])->json('data.id');
        $this->withHeaders($this->authHeaders('ISS-A02'))->postJson("/api/v1/stock/documents/{$issDoc}/lines", ['goods_id' => $this->goods->id, 'quantity' => 90]);
        $this->withHeaders($this->authHeaders('ISS-A03'))->postJson("/api/v1/stock/documents/{$issDoc}/submit");
        $this->withHeaders($this->authHeaders('ISS-A04'))->postJson("/api/v1/stock/documents/{$issDoc}/approve");
        $this->withHeaders($this->authHeaders('ISS-APOST'))->postJson("/api/v1/stock/documents/{$issDoc}/post");

        // 3. Attempt Reverse Receive 100 (Available is only 10) -> Rejected with 409 REVERSAL_INSUFFICIENT_STOCK
        $revRes = $this->withHeaders($this->authHeaders('REV-FAIL-01'))
            ->postJson("/api/v1/stock/documents/{$recDoc}/reverse", [
                'reason' => 'Want to cancel receive',
            ]);

        $revRes->assertStatus(409)
            ->assertJson([
                'success' => false,
                'code' => 'REVERSAL_INSUFFICIENT_STOCK',
            ]);

        // Stock remains 10
        $balance = StockBalance::where('goods_id', $this->goods->id)->where('location_id', $this->locA->id)->first();
        $this->assertEquals('10.0000', $balance->on_hand);
    }
}
