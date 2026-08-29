<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\InventoryTransaction\Models\StockBalance;
use Modules\InventoryTransaction\Models\StockLot;
use Modules\InventoryTransaction\Models\StockLotBalance;
use Modules\InventoryTransaction\Models\StockMovement;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;
use Tests\TestCase;

class LotStockPostingTest extends TestCase
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
            'email' => 'manager@test.com',
            'password' => 'password123',
        ]);
        $this->token = $login->json('data.access_token');

        $this->warehouse = Warehouse::where('organization_id', $this->orgId)->where('code', 'MAIN')->first();
        $this->locA = WarehouseLocation::where('warehouse_id', $this->warehouse->id)->where('code', 'A-01-01')->first();

        $product = \Modules\MasterData\Models\Product::where('organization_id', $this->orgId)->first();
        $unit = \Modules\MasterData\Models\Unit::where('organization_id', $this->orgId)->first();

        $this->lotGoods = Goods::create([
            'organization_id' => $this->orgId,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'sku' => 'LOT-TEST-' . uniqid(),
            'name' => 'Lot Test Goods ' . uniqid(),
            'pack_size' => 1,
            'is_lot_tracked' => true,
            'is_serial_tracked' => false,
            'is_active' => true,
        ]);
    }

    protected function authHeaders(string $idempotencyKey = 'LOT-KEY-001'): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'X-Organization-Id' => $this->orgId,
            'Idempotency-Key' => $idempotencyKey,
        ];
    }

    public function test_lot_receive_creates_lot_record_and_lot_balance(): void
    {
        $docRes = $this->withHeaders($this->authHeaders('LOT-REC-01'))
            ->postJson('/api/v1/stock/documents', [
                'document_type' => 'RECEIVE',
                'destination_warehouse_id' => $this->warehouse->id,
                'destination_location_id' => $this->locA->id,
            ]);
        $docId = $docRes->json('data.id');

        $futureExpiry = Carbon::now()->addYear()->toDateString();
        $mfgDate = Carbon::now()->subMonth()->toDateString();

        $this->withHeaders($this->authHeaders('LOT-REC-02'))->postJson("/api/v1/stock/documents/{$docId}/lines", [
            'goods_id' => $this->lotGoods->id,
            'quantity' => 100,
            'lot_no' => 'LOT-ALPHA',
            'manufactured_at' => $mfgDate,
            'expired_at' => $futureExpiry,
        ])->assertStatus(201);

        $this->withHeaders($this->authHeaders('LOT-REC-03'))->postJson("/api/v1/stock/documents/{$docId}/submit");
        $this->withHeaders($this->authHeaders('LOT-REC-04'))->postJson("/api/v1/stock/documents/{$docId}/approve");

        // POST
        $this->withHeaders($this->authHeaders('LOT-REC-POST'))
            ->postJson("/api/v1/stock/documents/{$docId}/post")
            ->assertStatus(200);

        // Verify Stock Lot created
        $lot = StockLot::where('organization_id', $this->orgId)
            ->where('goods_id', $this->lotGoods->id)
            ->where('lot_no', 'LOT-ALPHA')
            ->first();

        $this->assertNotNull($lot);
        $this->assertEquals($futureExpiry, Carbon::parse($lot->expired_at)->toDateString());

        // Verify Lot Balance = 100
        $lotBalance = StockLotBalance::where('organization_id', $this->orgId)
            ->where('warehouse_id', $this->warehouse->id)
            ->where('location_id', $this->locA->id)
            ->where('lot_id', $lot->id)
            ->first();

        $this->assertNotNull($lotBalance);
        $this->assertEquals('100.0000', $lotBalance->on_hand);

        // Verify Main Balance = 100
        $mainBalance = StockBalance::where('organization_id', $this->orgId)
            ->where('warehouse_id', $this->warehouse->id)
            ->where('location_id', $this->locA->id)
            ->where('goods_id', $this->lotGoods->id)
            ->first();

        $this->assertEquals('100.0000', $mainBalance->on_hand);

        // Verify Movement lot_id is populated
        $movement = StockMovement::where('document_id', $docId)->first();
        $this->assertEquals($lot->id, $movement->lot_id);
    }

    public function test_existing_lot_receives_additional_stock_without_duplicating_lot_record(): void
    {
        $futureExpiry = Carbon::now()->addYear()->toDateString();

        // 1. Existing Lot with 50 balance
        $lot = StockLot::firstOrCreate(
            [
                'organization_id' => $this->orgId,
                'goods_id' => $this->lotGoods->id,
                'lot_no' => 'LOT-BETA',
            ],
            [
                'expired_at' => $futureExpiry,
            ]
        );

        StockLotBalance::updateOrCreate(
            [
                'organization_id' => $this->orgId,
                'warehouse_id' => $this->warehouse->id,
                'location_id' => $this->locA->id,
                'lot_id' => $lot->id,
            ],
            [
                'on_hand' => '50.0000',
                'reserved' => '0.0000',
            ]
        );

        StockBalance::updateOrCreate(
            [
                'organization_id' => $this->orgId,
                'warehouse_id' => $this->warehouse->id,
                'location_id' => $this->locA->id,
                'goods_id' => $this->lotGoods->id,
            ],
            [
                'on_hand' => '50.0000',
                'reserved' => '0.0000',
            ]
        );

        // 2. Receive additional 20
        $docRes = $this->withHeaders($this->authHeaders('LOT-EX-01'))
            ->postJson('/api/v1/stock/documents', [
                'document_type' => 'RECEIVE',
                'destination_warehouse_id' => $this->warehouse->id,
                'destination_location_id' => $this->locA->id,
            ]);
        $docId = $docRes->json('data.id');

        $this->withHeaders($this->authHeaders('LOT-EX-02'))->postJson("/api/v1/stock/documents/{$docId}/lines", [
            'goods_id' => $this->lotGoods->id,
            'quantity' => 20,
            'lot_no' => 'LOT-BETA',
            'expired_at' => $futureExpiry,
        ]);
        $this->withHeaders($this->authHeaders('LOT-EX-03'))->postJson("/api/v1/stock/documents/{$docId}/submit");
        $this->withHeaders($this->authHeaders('LOT-EX-04'))->postJson("/api/v1/stock/documents/{$docId}/approve");

        // 3. POST
        $this->withHeaders($this->authHeaders('LOT-EX-POST'))
            ->postJson("/api/v1/stock/documents/{$docId}/post")
            ->assertStatus(200);

        // Verify total lot records count is still 1
        $this->assertEquals(1, StockLot::where('organization_id', $this->orgId)->where('goods_id', $this->lotGoods->id)->where('lot_no', 'LOT-BETA')->count());

        // Verify balances = 70
        $lotBalance = StockLotBalance::where('lot_id', $lot->id)->first();
        $this->assertEquals('70.0000', $lotBalance->on_hand);

        $mainBalance = StockBalance::where('goods_id', $this->lotGoods->id)->where('location_id', $this->locA->id)->first();
        $this->assertEquals('70.0000', $mainBalance->on_hand);
    }

    public function test_lot_metadata_conflict_is_rejected_at_post(): void
    {
        $expiry2027 = '2027-01-01';
        $expiry2028 = '2028-01-01';

        // Existing Lot with 2027 expiry
        StockLot::create([
            'organization_id' => $this->orgId,
            'goods_id' => $this->lotGoods->id,
            'lot_no' => 'LOT-CONFLICT',
            'expired_at' => $expiry2027,
        ]);

        // Create receive with 2028 expiry
        $docRes = $this->withHeaders($this->authHeaders('LOT-CONF-01'))
            ->postJson('/api/v1/stock/documents', [
                'document_type' => 'RECEIVE',
                'destination_warehouse_id' => $this->warehouse->id,
                'destination_location_id' => $this->locA->id,
            ]);
        $docId = $docRes->json('data.id');

        $this->withHeaders($this->authHeaders('LOT-CONF-02'))->postJson("/api/v1/stock/documents/{$docId}/lines", [
            'goods_id' => $this->lotGoods->id,
            'quantity' => 10,
            'lot_no' => 'LOT-CONFLICT',
            'expired_at' => $expiry2028,
        ]);
        $this->withHeaders($this->authHeaders('LOT-CONF-03'))->postJson("/api/v1/stock/documents/{$docId}/submit");
        $this->withHeaders($this->authHeaders('LOT-CONF-04'))->postJson("/api/v1/stock/documents/{$docId}/approve");

        // POST must reject with LOT_METADATA_CONFLICT
        $postRes = $this->withHeaders($this->authHeaders('LOT-CONF-POST'))
            ->postJson("/api/v1/stock/documents/{$docId}/post");

        $postRes->assertStatus(409)
            ->assertJson([
                'success' => false,
                'code' => 'LOT_METADATA_CONFLICT',
            ]);
    }

    public function test_issue_expired_lot_is_rejected(): void
    {
        $pastExpiry = Carbon::now()->subDays(10)->toDateString();

        $lot = StockLot::firstOrCreate(
            [
                'organization_id' => $this->orgId,
                'goods_id' => $this->lotGoods->id,
                'lot_no' => 'LOT-EXPIRED',
            ],
            [
                'expired_at' => $pastExpiry,
            ]
        );

        StockLotBalance::updateOrCreate(
            [
                'organization_id' => $this->orgId,
                'warehouse_id' => $this->warehouse->id,
                'location_id' => $this->locA->id,
                'lot_id' => $lot->id,
            ],
            [
                'on_hand' => '100.0000',
                'reserved' => '0.0000',
            ]
        );

        StockBalance::updateOrCreate(
            [
                'organization_id' => $this->orgId,
                'warehouse_id' => $this->warehouse->id,
                'location_id' => $this->locA->id,
                'goods_id' => $this->lotGoods->id,
            ],
            [
                'on_hand' => '100.0000',
                'reserved' => '0.0000',
            ]
        );

        // Create ISSUE for expired lot
        $docRes = $this->withHeaders($this->authHeaders('LOT-EXP-01'))
            ->postJson('/api/v1/stock/documents', [
                'document_type' => 'ISSUE',
                'source_warehouse_id' => $this->warehouse->id,
                'source_location_id' => $this->locA->id,
            ]);
        $docId = $docRes->json('data.id');

        $this->withHeaders($this->authHeaders('LOT-EXP-02'))->postJson("/api/v1/stock/documents/{$docId}/lines", [
            'goods_id' => $this->lotGoods->id,
            'lot_id' => $lot->id,
            'quantity' => 10,
        ]);
        $this->withHeaders($this->authHeaders('LOT-EXP-03'))->postJson("/api/v1/stock/documents/{$docId}/submit");
        $this->withHeaders($this->authHeaders('LOT-EXP-04'))->postJson("/api/v1/stock/documents/{$docId}/approve");

        // POST should be rejected with LOT_EXPIRED
        $postRes = $this->withHeaders($this->authHeaders('LOT-EXP-POST'))
            ->postJson("/api/v1/stock/documents/{$docId}/post");

        $postRes->assertStatus(409)
            ->assertJson([
                'success' => false,
                'code' => 'LOT_EXPIRED',
            ]);
    }
}
