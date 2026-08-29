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

class IssueReversalTest extends TestCase
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
            'sku' => 'REV-ISS-' . uniqid(),
            'name' => 'Rev Iss Goods',
            'pack_size' => 1,
            'is_lot_tracked' => false,
            'is_serial_tracked' => false,
            'is_active' => true,
        ]);
    }

    protected function authHeaders(string $key = 'REV-ISS-KEY-001'): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'X-Organization-Id' => $this->orgId,
            'Idempotency-Key' => $key,
        ];
    }

    public function test_standard_issue_reversal_restores_stock_and_creates_positive_compensating_movement(): void
    {
        // 1. Initial stock 100
        $balance = StockBalance::create([
            'organization_id' => $this->orgId,
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->locA->id,
            'goods_id' => $this->goods->id,
            'on_hand' => '100.0000',
            'reserved' => '0.0000',
        ]);

        // 2. Post Issue of 40 (Stock -> 60)
        $doc = $this->withHeaders($this->authHeaders('ISS-01'))->postJson('/api/v1/stock/documents', [
            'document_type' => 'ISSUE',
            'source_warehouse_id' => $this->warehouse->id,
            'source_location_id' => $this->locA->id,
        ])->json('data.id');

        $this->withHeaders($this->authHeaders('ISS-02'))->postJson("/api/v1/stock/documents/{$doc}/lines", [
            'goods_id' => $this->goods->id,
            'quantity' => 40,
        ]);
        $this->withHeaders($this->authHeaders('ISS-03'))->postJson("/api/v1/stock/documents/{$doc}/submit");
        $this->withHeaders($this->authHeaders('ISS-04'))->postJson("/api/v1/stock/documents/{$doc}/approve");
        $this->withHeaders($this->authHeaders('ISS-POST'))->postJson("/api/v1/stock/documents/{$doc}/post");

        $balance->refresh();
        $this->assertEquals('60.0000', $balance->on_hand);

        $origMovement = StockMovement::where('document_id', $doc)->first();
        $this->assertEquals('-40.0000', $origMovement->quantity_delta);

        // 3. Reverse Issue Document
        $revRes = $this->withHeaders($this->authHeaders('REV-ISS-POST-01'))
            ->postJson("/api/v1/stock/documents/{$doc}/reverse", [
                'reason' => 'Customer returned the goods in good condition',
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

        // 4. Verify Stock restored to 100
        $balance->refresh();
        $this->assertEquals('100.0000', $balance->on_hand);

        // 5. Verify Original Document is REVERSED
        $this->assertEquals(StockDocumentStatus::REVERSED, StockDocument::find($doc)->status);

        // 6. Verify Compensating Movement: +40, reversal_of = origMovement.id
        $compMovement = StockMovement::where('document_id', $revDocId)->first();
        $this->assertNotNull($compMovement);
        $this->assertEquals(StockMovementType::REVERSAL, $compMovement->movement_type);
        $this->assertEquals('40.0000', $compMovement->quantity_delta);
        $this->assertEquals($origMovement->id, $compMovement->reversal_of);
    }
}
