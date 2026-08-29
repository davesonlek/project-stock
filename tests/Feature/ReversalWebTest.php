<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\AuthenticationAudit\Models\User;
use Modules\InventoryTransaction\Enums\StockDocumentStatus;
use Modules\InventoryTransaction\Models\StockBalance;
use Modules\InventoryTransaction\Models\StockDocument;
use Modules\InventoryTransaction\Models\StockMovement;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;
use Tests\TestCase;

class ReversalWebTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected Organization $org;
    protected Warehouse $warehouse;
    protected WarehouseLocation $locA;
    protected Goods $goods;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@test.com')->first();
        $this->org = Organization::where('name', 'Global Supply Co.')->first();

        $this->warehouse = Warehouse::where('organization_id', $this->org->id)->where('code', 'MAIN')->first();
        $this->locA = WarehouseLocation::where('warehouse_id', $this->warehouse->id)->where('code', 'A-01-01')->first();
        $this->goods = Goods::where('organization_id', $this->org->id)->where('is_lot_tracked', false)->where('is_serial_tracked', false)->first();
    }

    protected function authSession(): array
    {
        return ['current_organization_id' => $this->org->id];
    }

    public function test_web_reversal_workflow(): void
    {
        // 1. Post Receive of 50
        $this->actingAs($this->admin)->withSession($this->authSession())
            ->post('/stock/documents', [
                'document_type' => 'RECEIVE',
                'destination_warehouse_id' => $this->warehouse->id,
                'destination_location_id' => $this->locA->id,
                'remarks' => 'Pre-reversal doc',
            ]);

        $doc = StockDocument::where('remarks', 'Pre-reversal doc')->first();

        $this->actingAs($this->admin)->withSession($this->authSession())
            ->post("/stock/documents/{$doc->id}/lines", [
                'goods_id' => $this->goods->id,
                'quantity' => 50,
            ]);

        $this->actingAs($this->admin)->withSession($this->authSession())->post("/stock/documents/{$doc->id}/submit");
        $this->actingAs($this->admin)->withSession($this->authSession())->post("/stock/documents/{$doc->id}/approve");
        $this->actingAs($this->admin)->withSession($this->authSession())->post("/stock/documents/{$doc->id}/post");

        $doc->refresh();
        $this->assertEquals(StockDocumentStatus::POSTED, $doc->status);

        // 2. Reverse document from web UI
        $this->actingAs($this->admin)->withSession($this->authSession())
            ->post("/stock/documents/{$doc->id}/reverse", [
                'reason' => 'Defective batch return',
            ])
            ->assertRedirect(route('stock.documents.show', $doc->id));

        $doc->refresh();
        $this->assertEquals(StockDocumentStatus::REVERSED, $doc->status);

        // Compensating document created
        $revDoc = StockDocument::where('reversal_of', $doc->id)->first();
        $this->assertNotNull($revDoc);
        $this->assertEquals(StockDocumentStatus::POSTED, $revDoc->status);
    }
}
