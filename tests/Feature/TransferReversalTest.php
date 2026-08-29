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

class TransferReversalTest extends TestCase
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
            'email' => 'admin@test.com',
            'password' => 'password123',
        ]);
        $this->token = $login->json('data.access_token');

        $this->warehouse = Warehouse::where('organization_id', $this->orgId)->where('code', 'MAIN')->first();
        $this->locA = WarehouseLocation::where('warehouse_id', $this->warehouse->id)->where('code', 'A-01-01')->first();
        $this->locB = WarehouseLocation::where('warehouse_id', $this->warehouse->id)->where('code', 'A-01-02')->first();

        $product = Product::where('organization_id', $this->orgId)->first();
        $unit = Unit::where('organization_id', $this->orgId)->first();

        $this->goods = Goods::create([
            'organization_id' => $this->orgId,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'sku' => 'REV-TRF-' . uniqid(),
            'name' => 'Rev Trf Goods',
            'pack_size' => 1,
            'is_lot_tracked' => false,
            'is_serial_tracked' => false,
            'is_active' => true,
        ]);
    }

    protected function authHeaders(string $key = 'REV-TRF-KEY-001'): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'X-Organization-Id' => $this->orgId,
            'Idempotency-Key' => $key,
        ];
    }

    public function test_transfer_reversal_restores_source_and_deducts_destination(): void
    {
        // Initial: Loc A = 100, Loc B = 0
        $balA = StockBalance::create([
            'organization_id' => $this->orgId,
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->locA->id,
            'goods_id' => $this->goods->id,
            'on_hand' => '100.0000',
            'reserved' => '0.0000',
        ]);
        $balB = StockBalance::create([
            'organization_id' => $this->orgId,
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->locB->id,
            'goods_id' => $this->goods->id,
            'on_hand' => '0.0000',
            'reserved' => '0.0000',
        ]);

        // Post Transfer of 50 (Loc A -> Loc B)
        $doc = $this->withHeaders($this->authHeaders('TRF-01'))->postJson('/api/v1/stock/documents', [
            'document_type' => 'TRANSFER',
            'source_warehouse_id' => $this->warehouse->id,
            'source_location_id' => $this->locA->id,
            'destination_warehouse_id' => $this->warehouse->id,
            'destination_location_id' => $this->locB->id,
        ])->json('data.id');

        $this->withHeaders($this->authHeaders('TRF-02'))->postJson("/api/v1/stock/documents/{$doc}/lines", [
            'goods_id' => $this->goods->id,
            'quantity' => 50,
        ]);
        $this->withHeaders($this->authHeaders('TRF-03'))->postJson("/api/v1/stock/documents/{$doc}/submit");
        $this->withHeaders($this->authHeaders('TRF-04'))->postJson("/api/v1/stock/documents/{$doc}/approve");
        $this->withHeaders($this->authHeaders('TRF-POST'))->postJson("/api/v1/stock/documents/{$doc}/post");

        $balA->refresh();
        $balB->refresh();
        $this->assertEquals('50.0000', $balA->on_hand);
        $this->assertEquals('50.0000', $balB->on_hand);

        // Reverse Transfer
        $revRes = $this->withHeaders($this->authHeaders('REV-TRF-01'))
            ->postJson("/api/v1/stock/documents/{$doc}/reverse", [
                'reason' => 'Transfer was made to wrong location',
            ]);

        $revRes->assertStatus(200);

        // Verify Balances restored: Loc A = 100, Loc B = 0
        $balA->refresh();
        $balB->refresh();
        $this->assertEquals('100.0000', $balA->on_hand);
        $this->assertEquals('0.0000', $balB->on_hand);

        // Verify Original Document is REVERSED
        $this->assertEquals(StockDocumentStatus::REVERSED, StockDocument::find($doc)->status);
    }

    public function test_transfer_reversal_fails_if_destination_has_insufficient_stock(): void
    {
        // Initial: Loc A = 100, Loc B = 0
        StockBalance::create([
            'organization_id' => $this->orgId,
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->locA->id,
            'goods_id' => $this->goods->id,
            'on_hand' => '100.0000',
            'reserved' => '0.0000',
        ]);
        StockBalance::create([
            'organization_id' => $this->orgId,
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->locB->id,
            'goods_id' => $this->goods->id,
            'on_hand' => '0.0000',
            'reserved' => '0.0000',
        ]);

        // Transfer 50 from Loc A -> Loc B
        $doc = $this->withHeaders($this->authHeaders('TRF-F01'))->postJson('/api/v1/stock/documents', [
            'document_type' => 'TRANSFER',
            'source_warehouse_id' => $this->warehouse->id,
            'source_location_id' => $this->locA->id,
            'destination_warehouse_id' => $this->warehouse->id,
            'destination_location_id' => $this->locB->id,
        ])->json('data.id');
        $this->withHeaders($this->authHeaders('TRF-F02'))->postJson("/api/v1/stock/documents/{$doc}/lines", ['goods_id' => $this->goods->id, 'quantity' => 50]);
        $this->withHeaders($this->authHeaders('TRF-F03'))->postJson("/api/v1/stock/documents/{$doc}/submit");
        $this->withHeaders($this->authHeaders('TRF-F04'))->postJson("/api/v1/stock/documents/{$doc}/approve");
        $this->withHeaders($this->authHeaders('TRF-FPOST'))->postJson("/api/v1/stock/documents/{$doc}/post");

        // Now Issue 40 from Loc B (Loc B on_hand becomes 10)
        $issDoc = $this->withHeaders($this->authHeaders('ISS-B01'))->postJson('/api/v1/stock/documents', [
            'document_type' => 'ISSUE',
            'source_warehouse_id' => $this->warehouse->id,
            'source_location_id' => $this->locB->id,
        ])->json('data.id');
        $this->withHeaders($this->authHeaders('ISS-B02'))->postJson("/api/v1/stock/documents/{$issDoc}/lines", ['goods_id' => $this->goods->id, 'quantity' => 40]);
        $this->withHeaders($this->authHeaders('ISS-B03'))->postJson("/api/v1/stock/documents/{$issDoc}/submit");
        $this->withHeaders($this->authHeaders('ISS-B04'))->postJson("/api/v1/stock/documents/{$issDoc}/approve");
        $this->withHeaders($this->authHeaders('ISS-BPOST'))->postJson("/api/v1/stock/documents/{$issDoc}/post");

        // Attempt Reverse Transfer 50 (Loc B only has 10) -> Rejected with 409 REVERSAL_INSUFFICIENT_STOCK
        $revRes = $this->withHeaders($this->authHeaders('REV-TRF-FAIL'))
            ->postJson("/api/v1/stock/documents/{$doc}/reverse", [
                'reason' => 'Reverse transfer',
            ]);

        $revRes->assertStatus(409)
            ->assertJson([
                'success' => false,
                'code' => 'REVERSAL_INSUFFICIENT_STOCK',
            ]);
    }
}
