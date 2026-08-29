<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\AuthenticationAudit\Models\User;
use Modules\InventoryTransaction\Enums\StockDocumentStatus;
use Modules\InventoryTransaction\Enums\StockDocumentType;
use Modules\InventoryTransaction\Models\StockDocument;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;
use Tests\TestCase;

class StockDocumentActionAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $staff;
    protected User $manager;
    protected User $admin;
    protected Organization $org;
    protected Warehouse $warehouse;
    protected WarehouseLocation $locA;
    protected Goods $goods;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->staff = User::where('email', 'staff@test.com')->first();
        $this->manager = User::where('email', 'manager@test.com')->first();
        $this->admin = User::where('email', 'admin@test.com')->first();
        $this->org = Organization::where('name', 'Global Supply Co.')->first();

        $this->warehouse = Warehouse::where('organization_id', $this->org->id)->where('code', 'MAIN')->first();
        $this->locA = WarehouseLocation::where('warehouse_id', $this->warehouse->id)->where('code', 'A-01-01')->first();
        $this->goods = Goods::where('organization_id', $this->org->id)->first();
    }

    protected function authSession(): array
    {
        return ['current_organization_id' => $this->org->id];
    }

    public function test_staff_cannot_approve_or_post(): void
    {
        $doc = StockDocument::create([
            'organization_id' => $this->org->id,
            'document_no' => 'TEST-AUTH-01',
            'document_type' => StockDocumentType::RECEIVE,
            'status' => StockDocumentStatus::PENDING,
            'destination_warehouse_id' => $this->warehouse->id,
            'destination_location_id' => $this->locA->id,
            'created_by' => $this->staff->id,
        ]);

        // Staff tries to approve -> 403
        $this->actingAs($this->staff)->withSession($this->authSession())
            ->post("/stock/documents/{$doc->id}/approve")
            ->assertStatus(403);

        $doc->update(['status' => StockDocumentStatus::APPROVED]);

        // Staff tries to post -> 403
        $this->actingAs($this->staff)->withSession($this->authSession())
            ->post("/stock/documents/{$doc->id}/post")
            ->assertStatus(403);
    }

    public function test_manager_cannot_reverse(): void
    {
        $doc = StockDocument::create([
            'organization_id' => $this->org->id,
            'document_no' => 'TEST-AUTH-02',
            'document_type' => StockDocumentType::RECEIVE,
            'status' => StockDocumentStatus::POSTED,
            'destination_warehouse_id' => $this->warehouse->id,
            'destination_location_id' => $this->locA->id,
            'created_by' => $this->manager->id,
            'posted_by' => $this->manager->id,
            'posted_at' => now(),
        ]);

        // Manager tries to reverse -> 403
        $this->actingAs($this->manager)->withSession($this->authSession())
            ->post("/stock/documents/{$doc->id}/reverse", ['reason' => 'Test reason'])
            ->assertStatus(403);
    }
}
