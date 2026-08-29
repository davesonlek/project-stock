<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\InventoryTransaction\Enums\StockDocumentStatus;
use Modules\InventoryTransaction\Models\StockBalance;
use Modules\InventoryTransaction\Models\StockDocument;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Product;
use Modules\MasterData\Models\Unit;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;
use Tests\TestCase;

class AdjustmentReversalTest extends TestCase
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
            'sku' => 'REV-ADJ-' . uniqid(),
            'name' => 'Rev Adj Goods',
            'pack_size' => 1,
            'is_lot_tracked' => false,
            'is_serial_tracked' => false,
            'is_active' => true,
        ]);
    }

    protected function authHeaders(string $key = 'REV-ADJ-KEY-001'): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'X-Organization-Id' => $this->orgId,
            'Idempotency-Key' => $key,
        ];
    }

    public function test_reverse_adjustment_positive_and_negative(): void
    {
        // Initial stock 100
        $balance = StockBalance::updateOrCreate(
            ['organization_id' => $this->orgId, 'warehouse_id' => $this->warehouse->id, 'location_id' => $this->locA->id, 'goods_id' => $this->goods->id],
            ['on_hand' => '100.0000', 'reserved' => '0.0000']
        );

        // 1. Adjustment to 120 (+20)
        $doc = $this->withHeaders($this->authHeaders('ADJ-01'))->postJson('/api/v1/stock/documents', [
            'document_type' => 'ADJUSTMENT',
            'source_warehouse_id' => $this->warehouse->id,
            'source_location_id' => $this->locA->id,
        ])->json('data.id');

        $this->withHeaders($this->authHeaders('ADJ-02'))->postJson("/api/v1/stock/documents/{$doc}/lines", [
            'goods_id' => $this->goods->id,
            'counted_quantity' => 120,
        ]);
        $this->withHeaders($this->authHeaders('ADJ-03'))->postJson("/api/v1/stock/documents/{$doc}/submit");
        $this->withHeaders($this->authHeaders('ADJ-04'))->postJson("/api/v1/stock/documents/{$doc}/approve");
        $this->withHeaders($this->authHeaders('ADJ-POST'))->postJson("/api/v1/stock/documents/{$doc}/post");

        $balance->refresh();
        $this->assertEquals('120.0000', $balance->on_hand);

        // Reverse Adjustment (+20 -> -20 -> back to 100)
        $this->withHeaders($this->authHeaders('REV-ADJ-01'))
            ->postJson("/api/v1/stock/documents/{$doc}/reverse", [
                'reason' => 'Inventory count recheck was false',
            ])
            ->assertStatus(200);

        $balance->refresh();
        $this->assertEquals('100.0000', $balance->on_hand);
        $this->assertEquals(StockDocumentStatus::REVERSED, StockDocument::find($doc)->status);
    }
}
