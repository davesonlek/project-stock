<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\InventoryTransaction\Models\StockBalance;
use Modules\InventoryTransaction\Models\StockMovement;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Product;
use Modules\MasterData\Models\Unit;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;
use Tests\TestCase;

class ReversalReconciliationTest extends TestCase
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
            'sku' => 'REC-REV-' . uniqid(),
            'name' => 'Recon Rev Goods',
            'pack_size' => 1,
            'is_lot_tracked' => false,
            'is_serial_tracked' => false,
            'is_active' => true,
        ]);
    }

    protected function authHeaders(string $key = 'REC-REV-KEY-001'): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'X-Organization-Id' => $this->orgId,
            'Idempotency-Key' => $key,
        ];
    }

    public function test_complete_reversal_ledger_reconciliation(): void
    {
        // 1. Initial State: 0 stock
        $balance = StockBalance::create([
            'organization_id' => $this->orgId,
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->locA->id,
            'goods_id' => $this->goods->id,
            'on_hand' => '0.0000',
            'reserved' => '0.0000',
        ]);

        // 2. Receive 100
        $recDoc = $this->withHeaders($this->authHeaders('RCV-R01'))->postJson('/api/v1/stock/documents', [
            'document_type' => 'RECEIVE',
            'destination_warehouse_id' => $this->warehouse->id,
            'destination_location_id' => $this->locA->id,
        ])->json('data.id');
        $this->withHeaders($this->authHeaders('RCV-R02'))->postJson("/api/v1/stock/documents/{$recDoc}/lines", ['goods_id' => $this->goods->id, 'quantity' => 100]);
        $this->withHeaders($this->authHeaders('RCV-R03'))->postJson("/api/v1/stock/documents/{$recDoc}/submit");
        $this->withHeaders($this->authHeaders('RCV-R04'))->postJson("/api/v1/stock/documents/{$recDoc}/approve");
        $this->withHeaders($this->authHeaders('RCV-RPOST'))->postJson("/api/v1/stock/documents/{$recDoc}/post");

        // 3. Issue 30
        $issDoc = $this->withHeaders($this->authHeaders('ISS-R01'))->postJson('/api/v1/stock/documents', [
            'document_type' => 'ISSUE',
            'source_warehouse_id' => $this->warehouse->id,
            'source_location_id' => $this->locA->id,
        ])->json('data.id');
        $this->withHeaders($this->authHeaders('ISS-R02'))->postJson("/api/v1/stock/documents/{$issDoc}/lines", ['goods_id' => $this->goods->id, 'quantity' => 30]);
        $this->withHeaders($this->authHeaders('ISS-R03'))->postJson("/api/v1/stock/documents/{$issDoc}/submit");
        $this->withHeaders($this->authHeaders('ISS-R04'))->postJson("/api/v1/stock/documents/{$issDoc}/approve");
        $this->withHeaders($this->authHeaders('ISS-RPOST'))->postJson("/api/v1/stock/documents/{$issDoc}/post");

        $balance->refresh();
        $this->assertEquals('70.0000', $balance->on_hand);

        // 4. Reverse Issue 30 (Stock -> 100)
        $this->withHeaders($this->authHeaders('REV-ISS-R01'))
            ->postJson("/api/v1/stock/documents/{$issDoc}/reverse", ['reason' => 'Reversing issue'])
            ->assertStatus(200);

        $balance->refresh();
        $this->assertEquals('100.0000', $balance->on_hand);

        // Check movements sum strictly equals on_hand (100)
        $movementSum = StockMovement::where('goods_id', $this->goods->id)->sum('quantity_delta');
        $this->assertEquals('100.0000', number_format((float) $movementSum, 4, '.', ''));
        $this->assertEquals($balance->on_hand, number_format((float) $movementSum, 4, '.', ''));

        // 5. Reverse Receive 100 (Stock -> 0)
        $this->withHeaders($this->authHeaders('REV-RCV-R01'))
            ->postJson("/api/v1/stock/documents/{$recDoc}/reverse", ['reason' => 'Reversing receive'])
            ->assertStatus(200);

        $balance->refresh();
        $this->assertEquals('0.0000', $balance->on_hand);

        // Check movements sum strictly equals on_hand (0)
        $finalMovementSum = StockMovement::where('goods_id', $this->goods->id)->sum('quantity_delta');
        $this->assertEquals('0.0000', number_format((float) $finalMovementSum, 4, '.', ''));
        $this->assertEquals($balance->on_hand, number_format((float) $finalMovementSum, 4, '.', ''));
    }
}
