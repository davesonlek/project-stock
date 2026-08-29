<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\GoodsSupplier;
use Modules\MasterData\Models\Supplier;
use Tests\TestCase;

class GoodsSupplierTest extends TestCase
{
    use DatabaseTransactions;

    protected string $token;
    protected string $orgId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $org = Organization::where('name', 'Global Supply Co.')->first();
        $this->orgId = $org->id;

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@test.com',
            'password' => 'password123',
        ]);
        $this->token = $login->json('data.access_token');
    }

    protected function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'X-Organization-Id' => $this->orgId,
        ];
    }

    public function test_setting_primary_supplier_unsets_other_primary_for_same_goods(): void
    {
        $goods = Goods::where('organization_id', $this->orgId)->first();

        // 1. Create Supplier 2
        $supplier2 = Supplier::create([
            'organization_id' => $this->orgId,
            'name' => 'Supplier Two Ltd',
            'is_active' => true,
        ]);

        // 2. Link Supplier 2 as primary = true
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/goods-suppliers', [
                'supplier_id' => $supplier2->id,
                'goods_id' => $goods->id,
                'supplier_sku' => 'SUP2-SKU',
                'purchase_price' => 12.00,
                'lead_time_days' => 5,
                'is_primary' => true,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'is_primary' => true,
                ],
            ]);

        // 3. Verify there is only 1 primary supplier for this goods
        $primaryCount = GoodsSupplier::where('goods_id', $goods->id)
            ->where('is_primary', true)
            ->count();

        $this->assertEquals(1, $primaryCount);
    }
}
