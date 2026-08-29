<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;
use Tests\TestCase;

class StockDocumentRbacTest extends TestCase
{
    use DatabaseTransactions;

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

        $this->warehouse = Warehouse::where('organization_id', $this->orgId)->where('code', 'MAIN')->first();
        $this->locA = WarehouseLocation::where('warehouse_id', $this->warehouse->id)->where('code', 'A-01-01')->first();
        $this->goods = Goods::where('organization_id', $this->orgId)->where('is_lot_tracked', false)->where('is_serial_tracked', false)->first();
    }

    private function getAuthHeaders(string $email): array
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'password123',
        ]);

        return [
            'Authorization' => 'Bearer ' . $response->json('data.access_token'),
            'X-Organization-Id' => $this->orgId,
        ];
    }

    public function test_staff_permissions_workflow(): void
    {
        $staffHeaders = $this->getAuthHeaders('staff@test.com');
        $managerHeaders = $this->getAuthHeaders('manager@test.com');

        // 1. Staff creates draft -> allowed
        $doc = $this->withHeaders($staffHeaders)
            ->postJson('/api/v1/stock/documents', [
                'document_type' => 'RECEIVE',
                'destination_warehouse_id' => $this->warehouse->id,
                'destination_location_id' => $this->locA->id,
            ])
            ->assertStatus(201)
            ->json('data');

        // 2. Staff adds line to own draft -> allowed
        $this->withHeaders($staffHeaders)
            ->postJson("/api/v1/stock/documents/{$doc['id']}/lines", [
                'goods_id' => $this->goods->id,
                'quantity' => 10,
            ])
            ->assertStatus(201);

        // 3. Staff submits own draft -> allowed
        $this->withHeaders($staffHeaders)
            ->postJson("/api/v1/stock/documents/{$doc['id']}/submit")
            ->assertStatus(200);

        // 4. Staff tries to approve -> forbidden 403
        $this->withHeaders($staffHeaders)
            ->postJson("/api/v1/stock/documents/{$doc['id']}/approve")
            ->assertStatus(403);

        // 5. Staff tries to cancel PENDING document -> forbidden 403
        $this->withHeaders($staffHeaders)
            ->postJson("/api/v1/stock/documents/{$doc['id']}/cancel", [
                'reason' => 'Changed mind',
            ])
            ->assertStatus(403);

        // 6. Manager approves -> allowed 200
        $this->withHeaders($managerHeaders)
            ->postJson("/api/v1/stock/documents/{$doc['id']}/approve")
            ->assertStatus(200);
    }
}
