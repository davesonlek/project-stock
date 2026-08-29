<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\InventoryTransaction\Enums\SerialNumberStatus;
use Modules\InventoryTransaction\Models\SerialNumber;
use Modules\InventoryTransaction\Models\StockBalance;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;
use Tests\TestCase;

class SerialStockPostingTest extends TestCase
{
    use DatabaseTransactions;

    protected string $token;
    protected string $orgId;
    protected Warehouse $warehouse;
    protected WarehouseLocation $locA;
    protected WarehouseLocation $locB;
    protected ?Goods $serialGoods = null;

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

        $found = Goods::where('organization_id', $this->orgId)
            ->where('is_serial_tracked', true)
            ->where('is_lot_tracked', false)
            ->first();

        if (!$found) {
            $product = \Modules\MasterData\Models\Product::where('organization_id', $this->orgId)->first();
            $unit = \Modules\MasterData\Models\Unit::where('organization_id', $this->orgId)->first();
            $found = Goods::create([
                'organization_id' => $this->orgId,
                'product_id' => $product->id,
                'unit_id' => $unit->id,
                'sku' => 'SN-ONLY-GOODS',
                'name' => 'Serial Only Goods',
                'pack_size' => 1,
                'is_lot_tracked' => false,
                'is_serial_tracked' => true,
                'is_active' => true,
            ]);
        }
        $this->serialGoods = $found;
    }

    protected function authHeaders(string $idempotencyKey = 'SER-KEY-001'): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'X-Organization-Id' => $this->orgId,
            'Idempotency-Key' => $idempotencyKey,
        ];
    }

    public function test_serial_receive_creates_in_stock_serial_numbers(): void
    {
        $docRes = $this->withHeaders($this->authHeaders('SER-REC-01'))
            ->postJson('/api/v1/stock/documents', [
                'document_type' => 'RECEIVE',
                'destination_warehouse_id' => $this->warehouse->id,
                'destination_location_id' => $this->locA->id,
            ]);
        $docId = $docRes->json('data.id');

        $this->withHeaders($this->authHeaders('SER-REC-02'))->postJson("/api/v1/stock/documents/{$docId}/lines", [
            'goods_id' => $this->serialGoods->id,
            'quantity' => 3,
            'serials' => ['SN-TEST-001', 'SN-TEST-002', 'SN-TEST-003'],
        ])->assertStatus(201);

        $this->withHeaders($this->authHeaders('SER-REC-03'))->postJson("/api/v1/stock/documents/{$docId}/submit");
        $this->withHeaders($this->authHeaders('SER-REC-04'))->postJson("/api/v1/stock/documents/{$docId}/approve");

        // POST
        $this->withHeaders($this->authHeaders('SER-REC-POST'))
            ->postJson("/api/v1/stock/documents/{$docId}/post")
            ->assertStatus(200);

        // Verify Serials created in IN_STOCK
        $serials = SerialNumber::where('organization_id', $this->orgId)
            ->whereIn('serial_no', ['SN-TEST-001', 'SN-TEST-002', 'SN-TEST-003'])
            ->get();

        $this->assertCount(3, $serials);
        foreach ($serials as $serial) {
            $this->assertEquals(SerialNumberStatus::IN_STOCK, $serial->status);
            $this->assertEquals($this->warehouse->id, $serial->warehouse_id);
            $this->assertEquals($this->locA->id, $serial->location_id);
        }
    }

    public function test_serial_issue_transitions_serial_to_issued(): void
    {
        // 1. Initial State: SN-ISSUE-1 is IN_STOCK
        $serial = SerialNumber::create([
            'organization_id' => $this->orgId,
            'goods_id' => $this->serialGoods->id,
            'serial_no' => 'SN-ISSUE-1',
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->locA->id,
            'status' => SerialNumberStatus::IN_STOCK,
        ]);

        StockBalance::updateOrCreate(
            ['organization_id' => $this->orgId, 'warehouse_id' => $this->warehouse->id, 'location_id' => $this->locA->id, 'goods_id' => $this->serialGoods->id],
            ['on_hand' => '1.0000', 'reserved' => '0.0000']
        );

        // 2. Create ISSUE
        $docRes = $this->withHeaders($this->authHeaders('SER-ISS-01'))
            ->postJson('/api/v1/stock/documents', [
                'document_type' => 'ISSUE',
                'source_warehouse_id' => $this->warehouse->id,
                'source_location_id' => $this->locA->id,
            ]);
        $docId = $docRes->json('data.id');

        $this->withHeaders($this->authHeaders('SER-ISS-02'))->postJson("/api/v1/stock/documents/{$docId}/lines", [
            'goods_id' => $this->serialGoods->id,
            'quantity' => 1,
            'serials' => ['SN-ISSUE-1'],
        ]);
        $this->withHeaders($this->authHeaders('SER-ISS-03'))->postJson("/api/v1/stock/documents/{$docId}/submit");
        $this->withHeaders($this->authHeaders('SER-ISS-04'))->postJson("/api/v1/stock/documents/{$docId}/approve");

        // 3. POST
        $this->withHeaders($this->authHeaders('SER-ISS-POST'))
            ->postJson("/api/v1/stock/documents/{$docId}/post")
            ->assertStatus(200);

        // 4. Verify Serial status = ISSUED, warehouse & location = null
        $serial->refresh();
        $this->assertEquals(SerialNumberStatus::ISSUED, $serial->status);
        $this->assertNull($serial->warehouse_id);
        $this->assertNull($serial->location_id);
    }

    public function test_serial_transfer_updates_serial_location(): void
    {
        // 1. Initial State: SN-TRF-1 is IN_STOCK at Loc A
        $serial = SerialNumber::create([
            'organization_id' => $this->orgId,
            'goods_id' => $this->serialGoods->id,
            'serial_no' => 'SN-TRF-1',
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->locA->id,
            'status' => SerialNumberStatus::IN_STOCK,
        ]);

        StockBalance::updateOrCreate(
            ['organization_id' => $this->orgId, 'warehouse_id' => $this->warehouse->id, 'location_id' => $this->locA->id, 'goods_id' => $this->serialGoods->id],
            ['on_hand' => '1.0000', 'reserved' => '0.0000']
        );
        StockBalance::updateOrCreate(
            ['organization_id' => $this->orgId, 'warehouse_id' => $this->warehouse->id, 'location_id' => $this->locB->id, 'goods_id' => $this->serialGoods->id],
            ['on_hand' => '0.0000', 'reserved' => '0.0000']
        );

        // 2. Create TRANSFER
        $docRes = $this->withHeaders($this->authHeaders('SER-TRF-01'))
            ->postJson('/api/v1/stock/documents', [
                'document_type' => 'TRANSFER',
                'source_warehouse_id' => $this->warehouse->id,
                'source_location_id' => $this->locA->id,
                'destination_warehouse_id' => $this->warehouse->id,
                'destination_location_id' => $this->locB->id,
            ]);
        $docId = $docRes->json('data.id');

        $this->withHeaders($this->authHeaders('SER-TRF-02'))->postJson("/api/v1/stock/documents/{$docId}/lines", [
            'goods_id' => $this->serialGoods->id,
            'quantity' => 1,
            'serials' => ['SN-TRF-1'],
        ]);
        $this->withHeaders($this->authHeaders('SER-TRF-03'))->postJson("/api/v1/stock/documents/{$docId}/submit");
        $this->withHeaders($this->authHeaders('SER-TRF-04'))->postJson("/api/v1/stock/documents/{$docId}/approve");

        // 3. POST
        $this->withHeaders($this->authHeaders('SER-TRF-POST'))
            ->postJson("/api/v1/stock/documents/{$docId}/post")
            ->assertStatus(200);

        // 4. Verify Serial is at Loc B and IN_STOCK
        $serial->refresh();
        $this->assertEquals(SerialNumberStatus::IN_STOCK, $serial->status);
        $this->assertEquals($this->warehouse->id, $serial->warehouse_id);
        $this->assertEquals($this->locB->id, $serial->location_id);
    }
}
