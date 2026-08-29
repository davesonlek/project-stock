<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;
use Tests\TestCase;

class StockDocumentImmutabilityTest extends TestCase
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

    public function test_cannot_submit_empty_document(): void
    {
        $doc = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/stock/documents', [
                'document_type' => 'RECEIVE',
                'destination_warehouse_id' => $this->warehouse->id,
                'destination_location_id' => $this->locA->id,
            ])->json('data');

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/stock/documents/{$doc['id']}/submit");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'DOCUMENT_HAS_NO_LINES',
            ]);
    }

    public function test_pending_document_is_strictly_immutable(): void
    {
        // 1. Create and submit document
        $doc = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/stock/documents', [
                'document_type' => 'RECEIVE',
                'destination_warehouse_id' => $this->warehouse->id,
                'destination_location_id' => $this->locA->id,
            ])->json('data');

        $line = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/stock/documents/{$doc['id']}/lines", [
                'goods_id' => $this->goods->id,
                'quantity' => 10,
            ])->json('data');

        $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/stock/documents/{$doc['id']}/submit")
            ->assertStatus(200);

        // 2. Try Header Update -> 409
        $this->withHeaders($this->authHeaders())
            ->putJson("/api/v1/stock/documents/{$doc['id']}", [
                'remarks' => 'Illegal edit on PENDING',
            ])
            ->assertStatus(409)
            ->assertJson(['code' => 'DOCUMENT_NOT_EDITABLE']);

        // 3. Try Add Line -> 409
        $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/stock/documents/{$doc['id']}/lines", [
                'goods_id' => $this->goods->id,
                'quantity' => 5,
            ])
            ->assertStatus(409)
            ->assertJson(['code' => 'DOCUMENT_NOT_EDITABLE']);

        // 4. Try Update Line -> 409
        $this->withHeaders($this->authHeaders())
            ->putJson("/api/v1/stock/documents/{$doc['id']}/lines/{$line['id']}", [
                'quantity' => 20,
            ])
            ->assertStatus(409)
            ->assertJson(['code' => 'DOCUMENT_NOT_EDITABLE']);

        // 5. Try Delete Line -> 409
        $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/v1/stock/documents/{$doc['id']}/lines/{$line['id']}")
            ->assertStatus(409)
            ->assertJson(['code' => 'DOCUMENT_NOT_EDITABLE']);
    }

    public function test_cannot_approve_draft_directly(): void
    {
        $doc = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/stock/documents', [
                'document_type' => 'RECEIVE',
                'destination_warehouse_id' => $this->warehouse->id,
                'destination_location_id' => $this->locA->id,
            ])->json('data');

        $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/stock/documents/{$doc['id']}/lines", [
                'goods_id' => $this->goods->id,
                'quantity' => 10,
            ]);

        // Attempt direct approve on DRAFT
        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/stock/documents/{$doc['id']}/approve");

        $response->assertStatus(409)
            ->assertJson(['code' => 'INVALID_DOCUMENT_STATE']);
    }
}
