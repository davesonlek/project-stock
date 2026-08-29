<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\AuthenticationAudit\Models\User;
use Tests\TestCase;

class StockBalanceWebTest extends TestCase
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

    public function test_inventory_screens_render_successfully(): void
    {
        $session = ['current_organization_id' => $this->org->id];

        $this->actingAs($this->user)->withSession($session)
            ->get('/inventory/stock')
            ->assertStatus(200)
            ->assertSee('Warehouse Stock Balances')
            ->assertSee('On Hand')
            ->assertSee('Available');

        $this->actingAs($this->user)->withSession($session)
            ->get('/inventory/lots')
            ->assertStatus(200)
            ->assertSee('Stock Lots');

        $this->actingAs($this->user)->withSession($session)
            ->get('/inventory/serials')
            ->assertStatus(200)
            ->assertSee('Piece-Level Serial Numbers');
    }
}
