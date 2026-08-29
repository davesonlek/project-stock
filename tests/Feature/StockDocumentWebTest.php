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

class StockDocumentWebTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected Organization $org;
    protected Warehouse $warehouse;
    protected WarehouseLocation $locA;
    protected WarehouseLocation $locB;
    protected Goods $goods;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@test.com')->first();
        $this->org = Organization::where('name', 'Global Supply Co.')->first();

        $this->warehouse = Warehouse::where('organization_id', $this->org->id)->where('code', 'MAIN')->first();
        $this->locA = WarehouseLocation::where('warehouse_id', $this->warehouse->id)->where('code', 'A-01-01')->first();
        $this->locB = WarehouseLocation::where('warehouse_id', $this->warehouse->id)->where('code', 'A-01-02')->first();
        $this->goods = Goods::where('organization_id', $this->org->id)->where('is_lot_tracked', false)->where('is_serial_tracked', false)->first();
    }

    protected function authSession(): array
    {
        return ['current_organization_id' => $this->org->id];
    }

    public function test_complete_stock_document_web_lifecycle(): void
    {
        // 1. View List & Create Screen
        $this->actingAs($this->admin)->withSession($this->authSession())
            ->get('/stock/documents')
            ->assertStatus(200)
            ->assertSee('Stock Documents');

        $this->actingAs($this->admin)->withSession($this->authSession())
            ->get('/stock/documents/create?type=RECEIVE')
            ->assertStatus(200)
            ->assertSee('Create New Stock Receive (Draft)');

        // 2. Create Draft Receive Document
        $this->actingAs($this->admin)->withSession($this->authSession())
            ->post('/stock/documents', [
                'document_type' => 'RECEIVE',
                'destination_warehouse_id' => $this->warehouse->id,
                'destination_location_id' => $this->locA->id,
                'remarks' => 'Web UI Test Receive',
            ])
            ->assertRedirect();

        $doc = StockDocument::where('remarks', 'Web UI Test Receive')->first();
        $this->assertNotNull($doc);
        $this->assertEquals(StockDocumentStatus::DRAFT, $doc->status);

        // 3. Add Line to Draft
        $this->actingAs($this->admin)->withSession($this->authSession())
            ->post("/stock/documents/{$doc->id}/lines", [
                'goods_id' => $this->goods->id,
                'quantity' => 50,
            ])
            ->assertRedirect(route('stock.documents.show', $doc->id));

        $this->assertEquals(1, $doc->lines()->count());

        // 4. Submit Draft -> PENDING
        $this->actingAs($this->admin)->withSession($this->authSession())
            ->post("/stock/documents/{$doc->id}/submit")
            ->assertRedirect(route('stock.documents.show', $doc->id));

        $doc->refresh();
        $this->assertEquals(StockDocumentStatus::PENDING, $doc->status);

        // 5. Approve PENDING -> APPROVED
        $this->actingAs($this->admin)->withSession($this->authSession())
            ->post("/stock/documents/{$doc->id}/approve")
            ->assertRedirect(route('stock.documents.show', $doc->id));

        $doc->refresh();
        $this->assertEquals(StockDocumentStatus::APPROVED, $doc->status);

        // 6. POST APPROVED -> POSTED
        $this->actingAs($this->admin)->withSession($this->authSession())
            ->post("/stock/documents/{$doc->id}/post")
            ->assertRedirect(route('stock.documents.show', $doc->id));

        $doc->refresh();
        $this->assertEquals(StockDocumentStatus::POSTED, $doc->status);

        // Verify stock movement created
        $this->assertEquals(1, StockMovement::where('document_id', $doc->id)->count());

        // 7. Reverse POSTED -> REVERSED
        $this->actingAs($this->admin)->withSession($this->authSession())
            ->post("/stock/documents/{$doc->id}/reverse", [
                'reason' => 'Web UI Test reversal',
            ])
            ->assertRedirect(route('stock.documents.show', $doc->id));

        $doc->refresh();
        $this->assertEquals(StockDocumentStatus::REVERSED, $doc->status);
    }
}
