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
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;
use Tests\TestCase;

class StockDocumentCrossTenantTest extends TestCase
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

    public function test_tenant_b_cannot_access_or_mutate_tenant_a_document_and_receives_404(): void
    {
        $whA = Warehouse::where('organization_id', $this->orgA->id)->first();
        $locA = WarehouseLocation::where('warehouse_id', $whA->id)->first();

        // 1. Create Document in Tenant A
        $createRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->tokenA,
            'X-Organization-Id' => $this->orgA->id,
        ])->postJson('/api/v1/stock/documents', [
            'document_type' => 'RECEIVE',
            'destination_warehouse_id' => $whA->id,
            'destination_location_id' => $locA->id,
        ]);
        $createRes->assertStatus(201);
        $doc = $createRes->json('data');

        // 2. Tenant B attempts to GET -> 404
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->tokenB,
            'X-Organization-Id' => $this->orgB->id,
        ])->getJson('/api/v1/stock/documents/' . $doc['id'])
            ->assertStatus(404)
            ->assertJson(['code' => 'STOCK_DOCUMENT_NOT_FOUND']);

        // 3. Tenant B attempts to SUBMIT -> 404
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->tokenB,
            'X-Organization-Id' => $this->orgB->id,
        ])->postJson("/api/v1/stock/documents/{$doc['id']}/submit")
            ->assertStatus(404)
            ->assertJson(['code' => 'STOCK_DOCUMENT_NOT_FOUND']);
    }

    public function test_cannot_reference_foreign_tenant_goods(): void
    {
        $whA = Warehouse::where('organization_id', $this->orgA->id)->first();
        $locA = WarehouseLocation::where('warehouse_id', $whA->id)->first();

        $prod = \Modules\MasterData\Models\Product::first();
        $unit = \Modules\MasterData\Models\Unit::first();

        // Create goods for Tenant B
        $goodsB = Goods::create([
            'organization_id' => $this->orgB->id,
            'product_id' => $prod->id,
            'unit_id' => $unit->id,
            'barcode' => '885000009999',
            'pack_size' => 1,
            'cost' => 10,
            'sell_price' => 15,
            'is_lot_tracked' => false,
            'is_serial_tracked' => false,
            'is_active' => true,
        ]);

        $docARes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->tokenA,
            'X-Organization-Id' => $this->orgA->id,
        ])->postJson('/api/v1/stock/documents', [
            'document_type' => 'RECEIVE',
            'destination_warehouse_id' => $whA->id,
            'destination_location_id' => $locA->id,
        ]);
        $docARes->assertStatus(201);
        $docA = $docARes->json('data');

        // Try adding Tenant B Goods into Tenant A document
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->tokenA,
            'X-Organization-Id' => $this->orgA->id,
        ])->postJson("/api/v1/stock/documents/{$docA['id']}/lines", [
            'goods_id' => $goodsB->id,
            'quantity' => 10,
        ])->assertStatus(422);
    }
}
