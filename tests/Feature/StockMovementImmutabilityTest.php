<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\InventoryTransaction\Models\StockMovement;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;
use Tests\TestCase;

class StockMovementImmutabilityTest extends TestCase
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
        $this->goods = Goods::where('organization_id', $this->orgId)->where('is_lot_tracked', false)->where('is_serial_tracked', false)->first();
    }

    protected function authHeaders(string $idempotencyKey = 'IMMUT-001'): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'X-Organization-Id' => $this->orgId,
            'Idempotency-Key' => $idempotencyKey,
        ];
    }

    public function test_stock_movement_cannot_be_updated(): void
    {
        // 1. Post a Receive to generate real StockMovement
        $docRes = $this->withHeaders($this->authHeaders('IMM-REC-01'))
            ->postJson('/api/v1/stock/documents', [
                'document_type' => 'RECEIVE',
                'destination_warehouse_id' => $this->warehouse->id,
                'destination_location_id' => $this->locA->id,
            ]);
        $docId = $docRes->json('data.id');

        $this->withHeaders($this->authHeaders('IMM-REC-02'))->postJson("/api/v1/stock/documents/{$docId}/lines", [
            'goods_id' => $this->goods->id,
            'quantity' => 15,
        ]);
        $this->withHeaders($this->authHeaders('IMM-REC-03'))->postJson("/api/v1/stock/documents/{$docId}/submit");
        $this->withHeaders($this->authHeaders('IMM-REC-04'))->postJson("/api/v1/stock/documents/{$docId}/approve");

        $this->withHeaders($this->authHeaders('IMM-REC-POST'))
            ->postJson("/api/v1/stock/documents/{$docId}/post")
            ->assertStatus(200);

        $movement = StockMovement::where('document_id', $docId)->first();
        $this->assertNotNull($movement);

        // 2. Direct SQL UPDATE must be rejected by trigger
        $this->expectException(QueryException::class);
        DB::statement('UPDATE stock_movements SET quantity_delta = 999.0000 WHERE id = ?', [$movement->id]);
    }

    public function test_stock_movement_cannot_be_deleted(): void
    {
        // 1. Post a Receive to generate real StockMovement
        $docRes = $this->withHeaders($this->authHeaders('IMM-DEL-01'))
            ->postJson('/api/v1/stock/documents', [
                'document_type' => 'RECEIVE',
                'destination_warehouse_id' => $this->warehouse->id,
                'destination_location_id' => $this->locA->id,
            ]);
        $docId = $docRes->json('data.id');

        $this->withHeaders($this->authHeaders('IMM-DEL-02'))->postJson("/api/v1/stock/documents/{$docId}/lines", [
            'goods_id' => $this->goods->id,
            'quantity' => 25,
        ]);
        $this->withHeaders($this->authHeaders('IMM-DEL-03'))->postJson("/api/v1/stock/documents/{$docId}/submit");
        $this->withHeaders($this->authHeaders('IMM-DEL-04'))->postJson("/api/v1/stock/documents/{$docId}/approve");

        $this->withHeaders($this->authHeaders('IMM-DEL-POST'))
            ->postJson("/api/v1/stock/documents/{$docId}/post")
            ->assertStatus(200);

        $movement = StockMovement::where('document_id', $docId)->first();
        $this->assertNotNull($movement);

        // 2. Direct SQL DELETE must be rejected by trigger
        $this->expectException(QueryException::class);
        DB::statement('DELETE FROM stock_movements WHERE id = ?', [$movement->id]);
    }
}
