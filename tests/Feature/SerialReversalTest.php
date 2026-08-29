<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\InventoryTransaction\Enums\SerialNumberStatus;
use Modules\InventoryTransaction\Models\SerialNumber;
use Modules\InventoryTransaction\Models\StockBalance;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Product;
use Modules\MasterData\Models\Unit;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;
use Tests\TestCase;

class SerialReversalTest extends TestCase
{
    use DatabaseTransactions;

    protected string $token;
    protected string $orgId;
    protected Warehouse $warehouse;
    protected WarehouseLocation $locA;
    protected WarehouseLocation $locB;
    protected Goods $serialGoods;

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

        $this->serialGoods = Goods::create([
            'organization_id' => $this->orgId,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'sku' => 'REV-SN-' . uniqid(),
            'name' => 'Rev Serial Goods',
            'pack_size' => 1,
            'is_lot_tracked' => false,
            'is_serial_tracked' => true,
            'is_active' => true,
        ]);
    }

    protected function authHeaders(string $key = 'REV-SN-KEY-001'): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'X-Organization-Id' => $this->orgId,
            'Idempotency-Key' => $key,
        ];
    }

    public function test_reverse_serial_receive_transitions_to_reversed(): void
    {
        $sn = 'SN-REV-RCV-' . uniqid();

        // Receive Serial
        $doc = $this->withHeaders($this->authHeaders('RCV-SN-01'))->postJson('/api/v1/stock/documents', [
            'document_type' => 'RECEIVE',
            'destination_warehouse_id' => $this->warehouse->id,
            'destination_location_id' => $this->locA->id,
        ])->json('data.id');

        $this->withHeaders($this->authHeaders('RCV-SN-02'))->postJson("/api/v1/stock/documents/{$doc}/lines", [
            'goods_id' => $this->serialGoods->id,
            'quantity' => 1,
            'serials' => [$sn],
        ]);
        $this->withHeaders($this->authHeaders('RCV-SN-03'))->postJson("/api/v1/stock/documents/{$doc}/submit");
        $this->withHeaders($this->authHeaders('RCV-SN-04'))->postJson("/api/v1/stock/documents/{$doc}/approve");
        $this->withHeaders($this->authHeaders('RCV-SN-POST'))->postJson("/api/v1/stock/documents/{$doc}/post");

        $serial = SerialNumber::where('serial_no', $sn)->first();
        $this->assertEquals(SerialNumberStatus::IN_STOCK, $serial->status);

        // Reverse Receive
        $this->withHeaders($this->authHeaders('REV-SN-RCV-01'))
            ->postJson("/api/v1/stock/documents/{$doc}/reverse", [
                'reason' => 'Vendor delivered wrong serial',
            ])
            ->assertStatus(200);

        // Serial status becomes REVERSED, warehouse/location null
        $serial->refresh();
        $this->assertEquals(SerialNumberStatus::REVERSED, $serial->status);
        $this->assertNull($serial->warehouse_id);
        $this->assertNull($serial->location_id);
    }

    public function test_reverse_serial_issue_restores_in_stock_at_original_location(): void
    {
        $sn = 'SN-REV-ISS-' . uniqid();

        // 1. Initial Serial in Loc A
        $serial = SerialNumber::create([
            'organization_id' => $this->orgId,
            'goods_id' => $this->serialGoods->id,
            'serial_no' => $sn,
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->locA->id,
            'status' => SerialNumberStatus::IN_STOCK,
        ]);

        StockBalance::create([
            'organization_id' => $this->orgId,
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->locA->id,
            'goods_id' => $this->serialGoods->id,
            'on_hand' => '1.0000',
            'reserved' => '0.0000',
        ]);

        // 2. Issue Serial
        $doc = $this->withHeaders($this->authHeaders('ISS-SN-01'))->postJson('/api/v1/stock/documents', [
            'document_type' => 'ISSUE',
            'source_warehouse_id' => $this->warehouse->id,
            'source_location_id' => $this->locA->id,
        ])->json('data.id');

        $this->withHeaders($this->authHeaders('ISS-SN-02'))->postJson("/api/v1/stock/documents/{$doc}/lines", [
            'goods_id' => $this->serialGoods->id,
            'quantity' => 1,
            'serials' => [$sn],
        ]);
        $this->withHeaders($this->authHeaders('ISS-SN-03'))->postJson("/api/v1/stock/documents/{$doc}/submit");
        $this->withHeaders($this->authHeaders('ISS-SN-04'))->postJson("/api/v1/stock/documents/{$doc}/approve");
        $this->withHeaders($this->authHeaders('ISS-SN-POST'))->postJson("/api/v1/stock/documents/{$doc}/post");

        $serial->refresh();
        $this->assertEquals(SerialNumberStatus::ISSUED, $serial->status);

        // 3. Reverse Issue
        $this->withHeaders($this->authHeaders('REV-SN-ISS-01'))
            ->postJson("/api/v1/stock/documents/{$doc}/reverse", [
                'reason' => 'Customer RMA return',
            ])
            ->assertStatus(200);

        // Serial status restored to IN_STOCK at Loc A
        $serial->refresh();
        $this->assertEquals(SerialNumberStatus::IN_STOCK, $serial->status);
        $this->assertEquals($this->warehouse->id, $serial->warehouse_id);
        $this->assertEquals($this->locA->id, $serial->location_id);
    }
}
