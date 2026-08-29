<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\AuthenticationAudit\Enums\RoleEnum;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\AuthenticationAudit\Models\Role;
use Modules\AuthenticationAudit\Models\User;
use Modules\AuthenticationAudit\Models\UserOrganization;
use Modules\MasterData\Models\Category;
use Modules\MasterData\Models\Product;
use Tests\TestCase;

class MasterDataCrossTenantSecurityTest extends TestCase
{
    use DatabaseTransactions;

    protected User $userA;
    protected User $userB;
    protected Organization $orgA;
    protected Organization $orgB;
    protected string $tokenA;
    protected string $tokenB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->userA = User::where('email', 'owner@test.com')->first();
        $this->orgA = Organization::where('name', 'Global Supply Co.')->first();

        $loginA = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@test.com',
            'password' => 'password123',
        ]);
        $this->tokenA = $loginA->json('data.access_token');

        // Create Tenant B
        $this->userB = User::firstOrCreate(
            ['email' => 'owner_b@test.com'],
            [
                'id' => (string) Str::uuid(),
                'username' => 'owner_b',
                'password' => Hash::make('password123'),
                'is_active' => true,
                'is_verified' => true,
            ]
        );

        $this->orgB = Organization::firstOrCreate(
            ['name' => 'Competitor Logistics Corp'],
            [
                'id' => (string) Str::uuid(),
                'created_by' => $this->userB->id,
            ]
        );

        $ownerRole = Role::where('code', RoleEnum::OWNER->value)->first();
        UserOrganization::firstOrCreate(
            [
                'user_id' => $this->userB->id,
                'organization_id' => $this->orgB->id,
            ],
            [
                'role_id' => $ownerRole->id,
            ]
        );

        $loginB = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner_b@test.com',
            'password' => 'password123',
        ]);
        $this->tokenB = $loginB->json('data.access_token');
    }

    public function test_tenant_b_cannot_view_tenant_a_product_and_receives_404(): void
    {
        $productA = Product::where('organization_id', $this->orgA->id)->first();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->tokenB,
            'X-Organization-Id' => $this->orgB->id,
        ])->getJson('/api/v1/products/' . $productA->id);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code' => 'MASTER_DATA_NOT_FOUND',
            ]);
    }

    public function test_tenant_b_cannot_update_tenant_a_product_and_receives_404(): void
    {
        $productA = Product::where('organization_id', $this->orgA->id)->first();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->tokenB,
            'X-Organization-Id' => $this->orgB->id,
        ])->putJson('/api/v1/products/' . $productA->id, [
            'name' => 'Hacked Name',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code' => 'MASTER_DATA_NOT_FOUND',
            ]);
    }

    public function test_tenant_b_cannot_reference_tenant_a_category_when_creating_product(): void
    {
        $categoryA = Category::where('organization_id', $this->orgA->id)->first();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->tokenB,
            'X-Organization-Id' => $this->orgB->id,
        ])->postJson('/api/v1/products', [
            'sku' => 'ORG-B-SKU-01',
            'name' => 'Org B Product',
            'category_id' => $categoryA->id, // Belongs to Org A!
        ]);

        $response->assertStatus(422);
    }
}
