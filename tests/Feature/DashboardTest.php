<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\AuthenticationAudit\Models\User;
use Tests\TestCase;

class DashboardTest extends TestCase
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

    public function test_dashboard_renders_with_kpis_and_metrics(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->org->id])
            ->get('/dashboard');

        $response->assertStatus(200)
            ->assertSee('Inventory Dashboard')
            ->assertSee('Total Available')
            ->assertSee('Total On Hand')
            ->assertSee('Total Reserved')
            ->assertSee('Stock Document Workflow Status')
            ->assertSee('Recent Stock Movements');
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }
}
