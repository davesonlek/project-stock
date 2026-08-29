<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\InventoryTransaction\Enums\StockDocumentStatus;
use Modules\InventoryTransaction\Enums\StockMovementType;
use Modules\InventoryTransaction\Models\StockBalance;
use Modules\InventoryTransaction\Models\StockDocument;
use Modules\InventoryTransaction\Models\StockLot;
use Modules\InventoryTransaction\Models\StockLotBalance;
use Modules\InventoryTransaction\Models\StockMovement;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Product;
use Modules\MasterData\Models\Unit;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;
use Tests\TestCase;

class LotReversalTest extends TestCase
{
    use DatabaseTransactions;

    protected string $token;
    protected string $orgId;
    protected Warehouse $warehouse;
    protected WarehouseLocation $locA;
    protected Goods $lotGoods;

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

        $this->lotGoods = Goods::create([
            'organization_id' => $this->orgId,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'sku' => 'REV-LOT-' . uniqid(),
            'name' => 'Rev Lot Goods',
            'pack_size' => 1,
            'is_lot_tracked' => true,
            'is_serial_tracked' => false,
            'is_active' => true,
        ]);
    }

    protected function authHeaders(string $key = 'REV-LOT-KEY-001'): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'X-Organization-Id' => $this->orgId,
            'Idempotency-Key' => $key,
        ];
    }

    public function test_reverse_lot_receive_and_issue(): void
    {
        $futureExp = Carbon::now()->addYear()->toDateString();

        // 1. Receive Lot (50 qty)
        $recDoc = $this->withHeaders($this->authHeaders('RCV-L-01'))->postJson('/api/v1/stock/documents', [
            'document_type' => 'RECEIVE',
            'destination_warehouse_id' => $this->warehouse->id,
            'destination_location_id' => $this->locA->id,
        ])->json('data.id');

        $this->withHeaders($this->authHeaders('RCV-L-02'))->postJson("/api/v1/stock/documents/{$recDoc}/lines", [
            'goods_id' => $this->lotGoods->id,
            'quantity' => 50,
            'lot_no' => 'LOT-REV-TEST-01',
            'expired_at' => $futureExp,
        ]);
        $this->withHeaders($this->authHeaders('RCV-L-03'))->postJson("/api/v1/stock/documents/{$recDoc}/submit");
        $this->withHeaders($this->authHeaders('RCV-L-04'))->postJson("/api/v1/stock/documents/{$recDoc}/approve");
        $this->withHeaders($this->authHeaders('RCV-L-POST'))->postJson("/api/v1/stock/documents/{$recDoc}/post");

        $lot = StockLot::where('lot_no', 'LOT-REV-TEST-01')->first();
        $this->assertNotNull($lot);

        $lotBal = StockLotBalance::where('lot_id', $lot->id)->where('location_id', $this->locA->id)->first();
        $this->assertEquals('50.0000', $lotBal->on_hand);

        // 2. Reverse Lot Receive -> lot on_hand decremented 50 -> 0
        $revRes = $this->withHeaders($this->authHeaders('REV-L-POST-01'))
            ->postJson("/api/v1/stock/documents/{$recDoc}/reverse", [
                'reason' => 'Lot damaged on delivery',
            ]);

        $revRes->assertStatus(200);

        $lotBal->refresh();
        $this->assertEquals('0.0000', $lotBal->on_hand);

        $compMovement = StockMovement::where('document_id', $revRes->json('data.id'))->first();
        $this->assertNotNull($compMovement);
        $this->assertEquals($lot->id, $compMovement->lot_id);
        $this->assertEquals('-50.0000', $compMovement->quantity_delta);
    }

    public function test_reverse_lot_receive_fails_when_lot_stock_is_insufficient(): void
    {
        $futureExp = Carbon::now()->addYear()->toDateString();

        // 1. Receive Lot 50
        $recDoc = $this->withHeaders($this->authHeaders('RCV-L-F01'))->postJson('/api/v1/stock/documents', [
            'document_type' => 'RECEIVE',
            'destination_warehouse_id' => $this->warehouse->id,
            'destination_location_id' => $this->locA->id,
        ])->json('data.id');
        $this->withHeaders($this->authHeaders('RCV-L-F02'))->postJson("/api/v1/stock/documents/{$recDoc}/lines", [
            'goods_id' => $this->lotGoods->id,
            'quantity' => 50,
            'lot_no' => 'LOT-REV-TEST-FAIL',
            'expired_at' => $futureExp,
        ]);
        $this->withHeaders($this->authHeaders('RCV-L-F03'))->postJson("/api/v1/stock/documents/{$recDoc}/submit");
        $this->withHeaders($this->authHeaders('RCV-L-F04'))->postJson("/api/v1/stock/documents/{$recDoc}/approve");
        $this->withHeaders($this->authHeaders('RCV-L-FPOST'))->postJson("/api/v1/stock/documents/{$recDoc}/post");

        $lot = StockLot::where('lot_no', 'LOT-REV-TEST-FAIL')->first();

        // 2. Issue 45 from this Lot (leaving 5 in lot)
        $issDoc = $this->withHeaders($this->authHeaders('ISS-L-F01'))->postJson('/api/v1/stock/documents', [
            'document_type' => 'ISSUE',
            'source_warehouse_id' => $this->warehouse->id,
            'source_location_id' => $this->locA->id,
        ])->json('data.id');
        $this->withHeaders($this->authHeaders('ISS-L-F02'))->postJson("/api/v1/stock/documents/{$issDoc}/lines", [
            'goods_id' => $this->lotGoods->id,
            'lot_id' => $lot->id,
            'quantity' => 45,
        ]);
        $this->withHeaders($this->authHeaders('ISS-L-F03'))->postJson("/api/v1/stock/documents/{$issDoc}/submit");
        $this->withHeaders($this->authHeaders('ISS-L-F04'))->postJson("/api/v1/stock/documents/{$issDoc}/approve");
        $this->withHeaders($this->authHeaders('ISS-L-FPOST'))->postJson("/api/v1/stock/documents/{$issDoc}/post");

        // 3. Attempt Reverse Receive 50 (lot only has 5 left) -> 409
        $this->withHeaders($this->authHeaders('REV-LOT-FAIL-01'))
            ->postJson("/api/v1/stock/documents/{$recDoc}/reverse", [
                'reason' => 'Should fail due to insufficient lot stock',
            ])
            ->assertStatus(409)
            ->assertJson([
                'success' => false,
                'code' => 'REVERSAL_INSUFFICIENT_STOCK',
            ]);
    }
}
