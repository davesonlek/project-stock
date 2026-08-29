<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Product;
use Modules\MasterData\Models\Unit;
use Tests\TestCase;

class GoodsTest extends TestCase
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

    public function test_can_create_goods_with_lot_and_serial_configuration(): void
    {
        $product = Product::where('organization_id', $this->orgId)->first();
        $unit = Unit::where('organization_id', $this->orgId)->where('code', 'PACK')->first();

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/goods', [
                'product_id' => $product->id,
                'unit_id' => $unit->id,
                'barcode' => '885999999999',
                'pack_size' => 12,
                'cost' => 120.00,
                'sell_price' => 180.00,
                'is_lot_tracked' => true,
                'is_serial_tracked' => true,
                'is_active' => true,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'pack_size' => 12,
                    'is_lot_tracked' => true,
                    'is_serial_tracked' => true,
                ],
            ]);
    }

    public function test_invalid_pack_size_or_negative_price_is_rejected(): void
    {
        $product = Product::where('organization_id', $this->orgId)->first();
        $unit = Unit::where('organization_id', $this->orgId)->first();

        // 1. Pack size <= 0
        $response1 = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/goods', [
                'product_id' => $product->id,
                'unit_id' => $unit->id,
                'pack_size' => 0,
                'cost' => 10,
                'sell_price' => 15,
            ]);
        $response1->assertStatus(422);

        // 2. Cost < 0
        $response2 = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/goods', [
                'product_id' => $product->id,
                'unit_id' => $unit->id,
                'pack_size' => 1,
                'cost' => -5,
                'sell_price' => 15,
            ]);
        $response2->assertStatus(422);
    }
}
