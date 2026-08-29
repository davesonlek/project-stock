<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\InventoryTransaction\Enums\StockReservationStatus;
use Modules\InventoryTransaction\Models\StockBalance;
use Modules\InventoryTransaction\Models\StockReservation;
use Modules\InventoryTransaction\Services\ExpireStockReservationService;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Product;
use Modules\MasterData\Models\Unit;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;
use Tests\TestCase;

class StockReservationExpirationTest extends TestCase
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
            'sku' => 'EXP-STD-' . uniqid(),
            'name' => 'Expire Std Goods',
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

    public function test_expire_overdue_reservation_restores_available_stock_and_marks_expired(): void
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
            'quantity' => 25,
        ])->json('data.id');

        $this->withHeaders($this->authHeaders())->postJson("/api/v1/stock/documents/{$doc}/submit");

        // Reserve with past expiry
        $resId = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/stock/documents/{$doc}/lines/{$lineId}/reserve", [
                'expires_at' => Carbon::now()->subMinute()->toISOString(),
            ])
            ->json('data.id');

        $balance->refresh();
        $this->assertEquals('25.0000', $balance->reserved);

        // Run expiration service
        $reservation = StockReservation::find($resId);
        $expireService = app(ExpireStockReservationService::class);
        $expireService->expire($reservation);

        // Verify status = EXPIRED, reserved = 0
        $reservation->refresh();
        $this->assertEquals(StockReservationStatus::EXPIRED, $reservation->status);

        $balance->refresh();
        $this->assertEquals('0.0000', $balance->reserved);
    }

    public function test_artisan_command_expires_all_overdue_active_reservations(): void
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
            'quantity' => 15,
        ])->json('data.id');

        $this->withHeaders($this->authHeaders())->postJson("/api/v1/stock/documents/{$doc}/submit");

        $resId = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/stock/documents/{$doc}/lines/{$lineId}/reserve", [
                'expires_at' => Carbon::now()->subMinutes(5)->toISOString(),
            ])
            ->json('data.id');

        // Run artisan command
        $this->artisan('stock:expire-reservations')
            ->assertExitCode(0);

        // Verify status = EXPIRED
        $this->assertEquals(StockReservationStatus::EXPIRED, StockReservation::find($resId)->status);
        $balance->refresh();
        $this->assertEquals('0.0000', $balance->reserved);
    }
}
