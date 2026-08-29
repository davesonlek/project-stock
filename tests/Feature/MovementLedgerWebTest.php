<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\AuthenticationAudit\Models\User;
use Modules\InventoryTransaction\Enums\StockMovementType;
use Modules\InventoryTransaction\Models\StockMovement;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;
use Tests\TestCase;

class MovementLedgerWebTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->user = User::where('email', 'manager@test.com')->first();
        $this->org = Organization::where('name', 'Global Supply Co.')->first();
    }

    public function test_movement_ledger_screen_renders_with_immutable_logs(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->org->id])
            ->get('/inventory/movements');

        $response->assertStatus(200)
            ->assertSee('Stock Movement Ledger')
            ->assertSee('Movement Type')
            ->assertSee('Quantity Delta')
            ->assertDontSee('Delete')
            ->assertDontSee('Edit');
    }
}
