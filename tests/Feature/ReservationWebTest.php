<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\AuthenticationAudit\Models\User;
use Modules\InventoryTransaction\Enums\StockDocumentStatus;
use Modules\InventoryTransaction\Enums\StockDocumentType;
use Modules\InventoryTransaction\Enums\StockReservationStatus;
use Modules\InventoryTransaction\Models\StockBalance;
use Modules\InventoryTransaction\Models\StockDocument;
use Modules\InventoryTransaction\Models\StockDocumentLine;
use Modules\InventoryTransaction\Models\StockReservation;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;
use Tests\TestCase;

class ReservationWebTest extends TestCase
{
    use DatabaseTransactions;

    protected User $manager;
    protected Organization $org;
    protected Warehouse $warehouse;
    protected WarehouseLocation $locA;
    protected Goods $goods;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->manager = User::where('email', 'manager@test.com')->first();
        $this->org = Organization::where('name', 'Global Supply Co.')->first();

        $this->warehouse = Warehouse::where('organization_id', $this->org->id)->where('code', 'MAIN')->first();
        $this->locA = WarehouseLocation::where('warehouse_id', $this->warehouse->id)->where('code', 'A-01-01')->first();
        $this->goods = Goods::where('organization_id', $this->org->id)->first();
    }

    protected function authSession(): array
    {
        return ['current_organization_id' => $this->org->id];
    }

    public function test_reservation_web_list_and_release(): void
    {
        // 1. Setup initial stock & active reservation
        StockBalance::updateOrCreate(
            ['organization_id' => $this->org->id, 'warehouse_id' => $this->warehouse->id, 'location_id' => $this->locA->id, 'goods_id' => $this->goods->id],
            ['on_hand' => '100.0000', 'reserved' => '20.0000']
        );

        $doc = StockDocument::create([
            'organization_id' => $this->org->id,
            'document_no' => 'TEST-RES-01',
            'document_type' => StockDocumentType::ISSUE,
            'status' => StockDocumentStatus::PENDING,
            'source_warehouse_id' => $this->warehouse->id,
            'source_location_id' => $this->locA->id,
            'created_by' => $this->manager->id,
        ]);

        $line = StockDocumentLine::create([
            'document_id' => $doc->id,
            'goods_id' => $this->goods->id,
            'line_number' => 1,
            'quantity' => 20,
        ]);

        $reservation = StockReservation::create([
            'organization_id' => $this->org->id,
            'document_id' => $doc->id,
            'document_line_id' => $line->id,
            'goods_id' => $this->goods->id,
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->locA->id,
            'quantity' => '20.0000',
            'status' => StockReservationStatus::ACTIVE,
            'created_by' => $this->manager->id,
        ]);

        // 2. View Reservations screen
        $this->actingAs($this->manager)->withSession($this->authSession())
            ->get('/inventory/reservations')
            ->assertStatus(200)
            ->assertSee('Stock Reservations')
            ->assertSee('ACTIVE');

        // 3. Release Reservation
        $this->actingAs($this->manager)->withSession($this->authSession())
            ->post("/inventory/reservations/{$reservation->id}/release")
            ->assertRedirect();

        $reservation->refresh();
        $this->assertEquals(StockReservationStatus::RELEASED, $reservation->status);
    }
}
