<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\AuthenticationAudit\Models\Role;
use Modules\AuthenticationAudit\Models\User;
use Modules\AuthenticationAudit\Models\UserOrganization;
use Tests\TestCase;

class OrganizationSelectorTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_user_can_view_organization_selector_and_see_memberships(): void
    {
        $user = User::where('email', 'admin@test.com')->first();

        $response = $this->actingAs($user)->get('/organizations/select');
        $response->assertStatus(200)
            ->assertSee('Select Organization')
            ->assertSee('Global Supply Co.');
    }

    public function test_user_can_select_valid_organization(): void
    {
        $user = User::where('email', 'admin@test.com')->first();
        $org = Organization::where('name', 'Global Supply Co.')->first();

        $response = $this->actingAs($user)->post('/organizations/select', [
            'organization_id' => $org->id,
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertEquals($org->id, session('current_organization_id'));
    }

    public function test_user_cannot_select_foreign_organization_without_membership(): void
    {
        $user = User::where('email', 'staff@test.com')->first();
        $admin = User::where('email', 'admin@test.com')->first();
        $foreignOrg = Organization::firstOrCreate(
            ['name' => 'Foreign Logistics Corp'],
            ['created_by' => $admin->id]
        );

        $response = $this->actingAs($user)->post('/organizations/select', [
            'organization_id' => $foreignOrg->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertNotEquals($foreignOrg->id, session('current_organization_id'));
    }
}
