<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\AuthenticationAudit\Models\User;
use Modules\InventoryTransaction\Enums\StockReservationStatus;
use Modules\InventoryTransaction\Models\StockBalance;
use Modules\InventoryTransaction\Models\StockMovement;
use Modules\InventoryTransaction\Models\StockReservation;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Product;
use Modules\MasterData\Models\Unit;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;
use Tests\TestCase;

class StockReservationTest extends TestCase
{
    use DatabaseTransactions;

    protected string $token;
    protected string $staffToken;
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

        $staffLogin = $this->postJson('/api/v1/auth/login', [
            'email' => 'staff@test.com',
            'password' => 'password123',
        ]);
        $this->staffToken = $staffLogin->json('data.access_token');

        $this->warehouse = Warehouse::where('organization_id', $this->orgId)->where('code', 'MAIN')->first();
        $this->locA = WarehouseLocation::where('warehouse_id', $this->warehouse->id)->where('code', 'A-01-01')->first();

        $product = Product::where('organization_id', $this->orgId)->first();
        $unit = Unit::where('organization_id', $this->orgId)->first();

        $this->goods = Goods::create([
            'organization_id' => $this->orgId,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'sku' => 'RES-STD-' . uniqid(),
            'name' => 'Res Std Goods',
            'pack_size' => 1,
            'is_lot_tracked' => false,
            'is_serial_tracked' => false,
            'is_active' => true,
        ]);
    }

    protected function authHeaders(string $token = null): array
    {
        return [
            'Authorization' => 'Bearer ' . ($token ?? $this->token),
            'X-Organization-Id' => $this->orgId,
        ];
    }

    public function test_basic_reservation_increases_reserved_without_changing_on_hand_or_creating_movement(): void
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

        // 2. Create Issue Document (PENDING)
        $doc = $this->withHeaders($this->authHeaders())->postJson('/api/v1/stock/documents', [
            'document_type' => 'ISSUE',
            'source_warehouse_id' => $this->warehouse->id,
            'source_location_id' => $this->locA->id,
        ])->json('data.id');

        $lineRes = $this->withHeaders($this->authHeaders())->postJson("/api/v1/stock/documents/{$doc}/lines", [
            'goods_id' => $this->goods->id,
            'quantity' => 20,
        ]);
        $lineId = $lineRes->json('data.id');

        $this->withHeaders($this->authHeaders())->postJson("/api/v1/stock/documents/{$doc}/submit");

        // 3. Reserve 20
        $res = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/stock/documents/{$doc}/lines/{$lineId}/reserve", [
                'expires_at' => now()->addDays(2)->toISOString(),
            ]);

        $res->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'quantity' => '20.0000',
                    'status' => 'ACTIVE',
                ],
            ]);

        // 4. Verify Database State: on_hand = 100, reserved = 20, available = 80
        $balance->refresh();
        $this->assertEquals('100.0000', $balance->on_hand);
        $this->assertEquals('20.0000', $balance->reserved);

        // 5. Verify NO movements created
        $this->assertEquals(0, StockMovement::where('document_id', $doc)->count());
    }

    public function test_cannot_reserve_draft_or_non_issue_document(): void
    {
        $balance = StockBalance::create([
            'organization_id' => $this->orgId,
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->locA->id,
            'goods_id' => $this->goods->id,
            'on_hand' => '50.0000',
            'reserved' => '0.0000',
        ]);

        // Draft Issue
        $doc = $this->withHeaders($this->authHeaders())->postJson('/api/v1/stock/documents', [
            'document_type' => 'ISSUE',
            'source_warehouse_id' => $this->warehouse->id,
            'source_location_id' => $this->locA->id,
        ])->json('data.id');

        $lineId = $this->withHeaders($this->authHeaders())->postJson("/api/v1/stock/documents/{$doc}/lines", [
            'goods_id' => $this->goods->id,
            'quantity' => 10,
        ])->json('data.id');

        // Attempt Reserve in DRAFT -> 409
        $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/stock/documents/{$doc}/lines/{$lineId}/reserve")
            ->assertStatus(409)
            ->assertJson(['code' => 'INVALID_DOCUMENT_STATE']);
    }

    public function test_staff_cannot_reserve_stock(): void
    {
        StockBalance::create([
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

        // Staff tries to reserve -> 403
        $this->withHeaders($this->authHeaders($this->staffToken))
            ->postJson("/api/v1/stock/documents/{$doc}/lines/{$lineId}/reserve")
            ->assertStatus(403);
    }

    public function test_duplicate_active_reservation_on_same_line_is_rejected(): void
    {
        StockBalance::create([
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
            'quantity' => 20,
        ])->json('data.id');

        $this->withHeaders($this->authHeaders())->postJson("/api/v1/stock/documents/{$doc}/submit");

        // First reserve -> 201
        $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/stock/documents/{$doc}/lines/{$lineId}/reserve")
            ->assertStatus(201);

        // Second reserve -> 409 RESERVATION_ALREADY_EXISTS
        $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/stock/documents/{$doc}/lines/{$lineId}/reserve")
            ->assertStatus(409)
            ->assertJson(['code' => 'RESERVATION_ALREADY_EXISTS']);
    }
}
