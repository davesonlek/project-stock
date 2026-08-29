<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\InventoryTransaction\Models\SerialNumber;
use Modules\InventoryTransaction\Models\StockLot;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;
use Tests\TestCase;

class StockDocumentLotAndSerialTest extends TestCase
{
    use DatabaseTransactions;

    protected string $token;
    protected string $orgId;
    protected Warehouse $warehouse;
    protected WarehouseLocation $locA;
    protected Goods $lotGoods;
    protected Goods $serialGoods;
    protected Goods $standardGoods;

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

        $this->lotGoods = Goods::where('organization_id', $this->orgId)->where('is_lot_tracked', true)->where('is_serial_tracked', false)->first();
        $this->serialGoods = Goods::where('organization_id', $this->orgId)->where('is_serial_tracked', true)->first();
        $this->standardGoods = Goods::where('organization_id', $this->orgId)->where('is_lot_tracked', false)->where('is_serial_tracked', false)->first();
    }

    protected function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'X-Organization-Id' => $this->orgId,
        ];
    }

    public function test_lot_receive_preparation_and_non_lot_rejection(): void
    {
        $lotCountBefore = StockLot::count();

        // 1. Create RECEIVE
        $doc = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/stock/documents', [
                'document_type' => 'RECEIVE',
                'destination_warehouse_id' => $this->warehouse->id,
                'destination_location_id' => $this->locA->id,
            ])->json('data');

        // 2. Add lot-tracked line with lot_no
        $lineRes = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/stock/documents/{$doc['id']}/lines", [
                'goods_id' => $this->lotGoods->id,
                'quantity' => 100,
                'lot_no' => 'LOT-2026-X1',
                'manufactured_at' => '2026-08-01',
                'expired_at' => '2027-08-01',
            ]);

        $lineRes->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'lot_no' => 'LOT-2026-X1',
                ],
            ]);

        // Submit & Approve
        $this->withHeaders($this->authHeaders())->postJson("/api/v1/stock/documents/{$doc['id']}/submit")->assertStatus(200);
        $this->withHeaders($this->authHeaders())->postJson("/api/v1/stock/documents/{$doc['id']}/approve")->assertStatus(200);

        // Verify NO stock_lots were created during workflow (only created on post)
        $this->assertEquals($lotCountBefore, StockLot::count());

        // 3. Standard goods cannot accept lot fields
        $doc2 = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/stock/documents', [
                'document_type' => 'RECEIVE',
                'destination_warehouse_id' => $this->warehouse->id,
                'destination_location_id' => $this->locA->id,
            ])->json('data');

        $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/stock/documents/{$doc2['id']}/lines", [
                'goods_id' => $this->standardGoods->id,
                'quantity' => 10,
                'lot_no' => 'INVALID-LOT',
            ])
            ->assertStatus(422)
            ->assertJson(['code' => 'LOT_NOT_ALLOWED']);
    }

    public function test_serial_receive_validation_and_preparation(): void
    {
        $doc = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/stock/documents', [
                'document_type' => 'RECEIVE',
                'destination_warehouse_id' => $this->warehouse->id,
                'destination_location_id' => $this->locA->id,
            ])->json('data');

        // 1. Serial count mismatch
        $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/stock/documents/{$doc['id']}/lines", [
                'goods_id' => $this->serialGoods->id,
                'quantity' => 3,
                'lot_no' => 'LOT-SN-1',
                'serials' => ['SN-001', 'SN-002'],
            ])
            ->assertStatus(422)
            ->assertJson(['code' => 'SERIAL_COUNT_MISMATCH']);

        // 2. Duplicate serial in input array
        $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/stock/documents/{$doc['id']}/lines", [
                'goods_id' => $this->serialGoods->id,
                'quantity' => 2,
                'lot_no' => 'LOT-SN-1',
                'serials' => ['SN-DUP-01', 'SN-DUP-01'],
            ])
            ->assertStatus(422)
            ->assertJson(['code' => 'DUPLICATE_SERIAL_NUMBER']);

        // 3. Valid Serial Receive
        $lineRes = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/stock/documents/{$doc['id']}/lines", [
                'goods_id' => $this->serialGoods->id,
                'quantity' => 2,
                'lot_no' => 'LOT-SN-1',
                'serials' => ['SN-VALID-01', 'SN-VALID-02'],
            ]);

        $lineRes->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'serials' => [
                        ['serial_no' => 'SN-VALID-01'],
                        ['serial_no' => 'SN-VALID-02'],
                    ],
                ],
            ]);
    }
}
