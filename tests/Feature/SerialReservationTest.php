<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\InventoryTransaction\Enums\SerialNumberStatus;
use Modules\InventoryTransaction\Enums\StockReservationStatus;
use Modules\InventoryTransaction\Models\SerialNumber;
use Modules\InventoryTransaction\Models\StockBalance;
use Modules\InventoryTransaction\Models\StockReservation;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Product;
use Modules\MasterData\Models\Unit;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;
use Tests\TestCase;

class SerialReservationTest extends TestCase
{
    use DatabaseTransactions;

    protected string $token;
    protected string $orgId;
    protected Warehouse $warehouse;
    protected WarehouseLocation $locA;
    protected Goods $serialGoods;

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

        $product = Product::where('organization_id', $this->orgId)->first();
        $unit = Unit::where('organization_id', $this->orgId)->first();

        $this->serialGoods = Goods::create([
            'organization_id' => $this->orgId,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'sku' => 'SN-RES-' . uniqid(),
            'name' => 'Serial Res Goods',
            'pack_size' => 1,
            'is_lot_tracked' => false,
            'is_serial_tracked' => true,
            'is_active' => true,
        ]);
    }

    protected function authHeaders(string $key = 'SN-RES-001'): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'X-Organization-Id' => $this->orgId,
            'Idempotency-Key' => $key,
        ];
    }

    public function test_serial_reservation_reserve_and_consume(): void
    {
        $serialNo = 'SN-RES-VAL-' . uniqid();
        $serial = SerialNumber::create([
            'organization_id' => $this->orgId,
            'goods_id' => $this->serialGoods->id,
            'serial_no' => $serialNo,
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

        // 1. Create Issue Document (1 qty)
        $doc = $this->withHeaders($this->authHeaders('S-RES-01'))->postJson('/api/v1/stock/documents', [
            'document_type' => 'ISSUE',
            'source_warehouse_id' => $this->warehouse->id,
            'source_location_id' => $this->locA->id,
        ])->json('data.id');

        $lineId = $this->withHeaders($this->authHeaders('S-RES-02'))->postJson("/api/v1/stock/documents/{$doc}/lines", [
            'goods_id' => $this->serialGoods->id,
            'quantity' => 1,
            'serials' => [$serialNo],
        ])->json('data.id');

        $this->withHeaders($this->authHeaders('S-RES-03'))->postJson("/api/v1/stock/documents/{$doc}/submit");

        // 2. Reserve Serial
        $resId = $this->withHeaders($this->authHeaders('S-RES-04'))
            ->postJson("/api/v1/stock/documents/{$doc}/lines/{$lineId}/reserve")
            ->json('data.id');

        // Verify serial status is RESERVED
        $serial->refresh();
        $this->assertEquals(SerialNumberStatus::RESERVED, $serial->status);

        // 3. Approve and POST -> Consume
        $this->withHeaders($this->authHeaders('S-RES-05'))->postJson("/api/v1/stock/documents/{$doc}/approve");

        $this->withHeaders($this->authHeaders('S-POST-01'))
            ->postJson("/api/v1/stock/documents/{$doc}/post")
            ->assertStatus(200);

        // Verify serial status is ISSUED, warehouse/location null
        $serial->refresh();
        $this->assertEquals(SerialNumberStatus::ISSUED, $serial->status);
        $this->assertNull($serial->warehouse_id);
        $this->assertNull($serial->location_id);

        // Reservation CONSUMED
        $this->assertEquals(StockReservationStatus::CONSUMED, StockReservation::find($resId)->status);
    }

    public function test_serial_reservation_release_restores_in_stock(): void
    {
        $serialNo = 'SN-REL-VAL-' . uniqid();
        $serial = SerialNumber::create([
            'organization_id' => $this->orgId,
            'goods_id' => $this->serialGoods->id,
            'serial_no' => $serialNo,
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

        $doc = $this->withHeaders($this->authHeaders('S-REL-01'))->postJson('/api/v1/stock/documents', [
            'document_type' => 'ISSUE',
            'source_warehouse_id' => $this->warehouse->id,
            'source_location_id' => $this->locA->id,
        ])->json('data.id');

        $lineId = $this->withHeaders($this->authHeaders('S-REL-02'))->postJson("/api/v1/stock/documents/{$doc}/lines", [
            'goods_id' => $this->serialGoods->id,
            'quantity' => 1,
            'serials' => [$serialNo],
        ])->json('data.id');

        $this->withHeaders($this->authHeaders('S-REL-03'))->postJson("/api/v1/stock/documents/{$doc}/submit");

        $resId = $this->withHeaders($this->authHeaders('S-REL-04'))
            ->postJson("/api/v1/stock/documents/{$doc}/lines/{$lineId}/reserve")
            ->json('data.id');

        $serial->refresh();
        $this->assertEquals(SerialNumberStatus::RESERVED, $serial->status);

        // Release reservation
        $this->withHeaders($this->authHeaders('S-REL-05'))
            ->postJson("/api/v1/stock/reservations/{$resId}/release")
            ->assertStatus(200);

        // Serial status restored to IN_STOCK
        $serial->refresh();
        $this->assertEquals(SerialNumberStatus::IN_STOCK, $serial->status);
        $this->assertEquals($this->warehouse->id, $serial->warehouse_id);
        $this->assertEquals($this->locA->id, $serial->location_id);
    }
}
