<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\InventoryTransaction\Models\StockBalance;
use Modules\InventoryTransaction\Models\StockDocument;
use Modules\InventoryTransaction\Models\StockMovement;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Product;
use Modules\MasterData\Models\Unit;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;
use Tests\TestCase;

class ReversalIdempotencyTest extends TestCase
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
            'sku' => 'IDEM-REV-' . uniqid(),
            'name' => 'Idem Rev Goods',
            'pack_size' => 1,
            'is_lot_tracked' => false,
            'is_serial_tracked' => false,
            'is_active' => true,
        ]);
    }

    protected function authHeaders(string $key = 'IDEM-REV-001'): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'X-Organization-Id' => $this->orgId,
            'Idempotency-Key' => $key,
        ];
    }

    public function test_reversal_idempotency_replay_and_conflict(): void
    {
        // 1. Post Receive 50
        $doc = $this->withHeaders($this->authHeaders('RCV-IDEM-01'))->postJson('/api/v1/stock/documents', [
            'document_type' => 'RECEIVE',
            'destination_warehouse_id' => $this->warehouse->id,
            'destination_location_id' => $this->locA->id,
        ])->json('data.id');

        $this->withHeaders($this->authHeaders('RCV-IDEM-02'))->postJson("/api/v1/stock/documents/{$doc}/lines", ['goods_id' => $this->goods->id, 'quantity' => 50]);
        $this->withHeaders($this->authHeaders('RCV-IDEM-03'))->postJson("/api/v1/stock/documents/{$doc}/submit");
        $this->withHeaders($this->authHeaders('RCV-IDEM-04'))->postJson("/api/v1/stock/documents/{$doc}/approve");
        $this->withHeaders($this->authHeaders('RCV-IDEM-POST'))->postJson("/api/v1/stock/documents/{$doc}/post");

        // 2. First Reversal with Key "REV-KEY-ABC"
        $revRes1 = $this->withHeaders($this->authHeaders('REV-KEY-ABC'))
            ->postJson("/api/v1/stock/documents/{$doc}/reverse", [
                'reason' => 'Vendor cancellation',
            ]);

        $revRes1->assertStatus(200);
        $revDocId = $revRes1->json('data.id');

        // Total documents with reversal_of = $doc should be 1
        $this->assertEquals(1, StockDocument::where('reversal_of', $doc)->count());

        // 3. Exact Replay with Same Key "REV-KEY-ABC" -> Returns 200
        $revRes2 = $this->withHeaders($this->authHeaders('REV-KEY-ABC'))
            ->postJson("/api/v1/stock/documents/{$doc}/reverse", [
                'reason' => 'Vendor cancellation',
            ]);

        $revRes2->assertStatus(200);
        $this->assertEquals(1, StockDocument::where('reversal_of', $doc)->count());

        // 4. Same Key with Different Payload -> 409 IDEMPOTENCY_KEY_CONFLICT
        $revRes3 = $this->withHeaders($this->authHeaders('REV-KEY-ABC'))
            ->postJson("/api/v1/stock/documents/{$doc}/reverse", [
                'reason' => 'Different Reason Conflict',
            ]);

        $revRes3->assertStatus(409)
            ->assertJson([
                'success' => false,
                'code' => 'IDEMPOTENCY_KEY_CONFLICT',
            ]);

        // 5. Different Key on Already Reversed Document -> 409 DOCUMENT_ALREADY_REVERSED
        $revRes4 = $this->withHeaders($this->authHeaders('REV-KEY-DIFFERENT'))
            ->postJson("/api/v1/stock/documents/{$doc}/reverse", [
                'reason' => 'Second attempt new key',
            ]);

        $revRes4->assertStatus(409)
            ->assertJson([
                'success' => false,
                'code' => 'DOCUMENT_ALREADY_REVERSED',
            ]);
    }
}
