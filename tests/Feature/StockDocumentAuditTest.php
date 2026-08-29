<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;
use Tests\TestCase;

class StockDocumentAuditTest extends TestCase
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

    protected function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'X-Organization-Id' => $this->orgId,
        ];
    }

    public function test_document_lifecycle_records_audit_trail(): void
    {
        // 1. Create Draft
        $doc = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/stock/documents', [
                'document_type' => 'RECEIVE',
                'destination_warehouse_id' => $this->warehouse->id,
                'destination_location_id' => $this->locA->id,
            ])->json('data');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'STOCK_DOCUMENT_CREATED',
            'entity_type' => 'StockDocument',
            'entity_id' => $doc['id'],
            'organization_id' => $this->orgId,
        ]);

        // 2. Add Line
        $line = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/stock/documents/{$doc['id']}/lines", [
                'goods_id' => $this->goods->id,
                'quantity' => 10,
            ])->json('data');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'STOCK_DOCUMENT_LINE_CREATED',
            'entity_type' => 'StockDocumentLine',
            'entity_id' => (string) $line['id'],
            'organization_id' => $this->orgId,
        ]);

        // 3. Submit
        $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/stock/documents/{$doc['id']}/submit");

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'STOCK_DOCUMENT_SUBMITTED',
            'entity_type' => 'StockDocument',
            'entity_id' => $doc['id'],
            'organization_id' => $this->orgId,
        ]);

        // 4. Approve
        $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/stock/documents/{$doc['id']}/approve");

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'STOCK_DOCUMENT_APPROVED',
            'entity_type' => 'StockDocument',
            'entity_id' => $doc['id'],
            'organization_id' => $this->orgId,
        ]);
    }
}
