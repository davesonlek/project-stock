<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\InventoryTransaction\Enums\StockDocumentStatus;
use Modules\InventoryTransaction\Enums\StockReservationStatus;
use Modules\InventoryTransaction\Models\StockBalance;
use Modules\InventoryTransaction\Models\StockDocument;
use Modules\InventoryTransaction\Models\StockReservation;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Product;
use Modules\MasterData\Models\Unit;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;
use Tests\TestCase;

class StockReservationReleaseTest extends TestCase
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
            'email' => 'manager@test.com',
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
            'sku' => 'REL-STD-' . uniqid(),
            'name' => 'Release Std Goods',
            'pack_size' => 1,
            'is_lot_tracked' => false,
            'is_serial_tracked' => false,
            'is_active' => true,
        ]);
    }

    protected function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'X-Organization-Id' => $this->orgId,
        ];
    }

    public function test_release_active_reservation_restores_available_stock_and_marks_released(): void
    {
        // 1. Initial Stock: on_hand = 100, reserved = 0
        $balance = StockBalance::create([
            'organization_id' => $this->orgId,
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->locA->id,
            'goods_id' => $this->goods->id,
            'on_hand' => '100.0000',
            'reserved' => '0.0000',
        ]);

        // 2. Create Issue (PENDING) and Reserve 20
        $doc = $this->withHeaders($this->authHeaders())->postJson('/api/v1/stock/documents', [
            'document_type' => 'ISSUE',
            'source_warehouse_id' => $this->warehouse->id,
            'source_location_id' => $this->locA->id,
        ])->json('data.id');

        $lineId = $this->withHeaders($this->authHeaders())->postJson("/api/v1/stock/documents/{$doc}/lines", [
            'goods_id' => $this->goods->id,
            'quantity' => 20,
        ])->json('data.id');

        $this->withHeaders($this->authHeaders())->postJson("/api/v1/stock/documents/{$doc}/submit");

        $res = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/stock/documents/{$doc}/lines/{$lineId}/reserve");
        $resId = $res->json('data.id');

        $balance->refresh();
        $this->assertEquals('20.0000', $balance->reserved);

        // 3. Release Reservation
        $releaseRes = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/stock/reservations/{$resId}/release");

        $releaseRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $resId,
                    'status' => 'RELEASED',
                ],
            ]);

        // 4. Verify Database State: on_hand = 100, reserved = 0, available = 100
        $balance->refresh();
        $this->assertEquals('100.0000', $balance->on_hand);
        $this->assertEquals('0.0000', $balance->reserved);

        $reservation = StockReservation::find($resId);
        $this->assertEquals(StockReservationStatus::RELEASED, $reservation->status);
        $this->assertNotNull($reservation->released_by);
        $this->assertNotNull($reservation->released_at);
    }

    public function test_releasing_already_released_reservation_returns_409(): void
    {
        $balance = StockBalance::create([
            'organization_id' => $this->orgId,
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->locA->id,
            'goods_id' => $this->goods->id,
            'on_hand' => '50.0000',
            'reserved' => '0.0000',
        ]);

        $doc = $this->withHeaders($this->authHeaders())->postJson('/api/v1/stock/documents', [
            'document_type' => 'ISSUE',
            'source_warehouse_id' => $this->warehouse->id,
            'source_location_id' => $this->locA->id,
        ])->json('data.id');

        $lineId = $this->withHeaders($this->authHeaders())->postJson("/api/v1/stock/documents/{$doc}/lines", [
            'goods_id' => $this->goods->id,
            'quantity' => 10,
        ])->json('data.id');

        $this->withHeaders($this->authHeaders())->postJson("/api/v1/stock/documents/{$doc}/submit");

        $resId = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/stock/documents/{$doc}/lines/{$lineId}/reserve")
            ->json('data.id');

        // First release -> 200
        $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/stock/reservations/{$resId}/release")
            ->assertStatus(200);

        // Second release -> 409
        $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/stock/reservations/{$resId}/release")
            ->assertStatus(409)
            ->assertJson(['code' => 'INVALID_RESERVATION_STATE']);
    }

    public function test_cancelling_pending_document_automatically_releases_all_active_reservations(): void
    {
        $balance = StockBalance::create([
            'organization_id' => $this->orgId,
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->locA->id,
            'goods_id' => $this->goods->id,
            'on_hand' => '100.0000',
            'reserved' => '0.0000',
        ]);

        $doc = $this->withHeaders($this->authHeaders())->postJson('/api/v1/stock/documents', [
            'document_type' => 'ISSUE',
            'source_warehouse_id' => $this->warehouse->id,
            'source_location_id' => $this->locA->id,
        ])->json('data.id');

        $lineId = $this->withHeaders($this->authHeaders())->postJson("/api/v1/stock/documents/{$doc}/lines", [
            'goods_id' => $this->goods->id,
            'quantity' => 30,
        ])->json('data.id');

        $this->withHeaders($this->authHeaders())->postJson("/api/v1/stock/documents/{$doc}/submit");

        $resId = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/stock/documents/{$doc}/lines/{$lineId}/reserve")
            ->json('data.id');

        $balance->refresh();
        $this->assertEquals('30.0000', $balance->reserved);

        // Cancel Document
        $cancelRes = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/stock/documents/{$doc}/cancel", [
                'reason' => 'Customer cancelled order',
            ]);

        $cancelRes->assertStatus(200);

        // Verify Document status = CANCELLED
        $this->assertEquals(StockDocumentStatus::CANCELLED, StockDocument::find($doc)->status);

        // Verify Reservation status = RELEASED
        $this->assertEquals(StockReservationStatus::RELEASED, StockReservation::find($resId)->status);

        // Verify Stock reserved reset to 0
        $balance->refresh();
        $this->assertEquals('0.0000', $balance->reserved);
    }
}
