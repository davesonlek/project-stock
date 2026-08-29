<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\MasterData\Models\Brand;
use Modules\MasterData\Models\Category;
use Modules\MasterData\Models\Product;
use Tests\TestCase;

class ProductTest extends TestCase
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

    public function test_can_list_products_with_filters_and_pagination(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/products?search=coca&is_active=1&per_page=10');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'sku', 'barcode', 'name', 'description',
                        'category_id', 'brand_id', 'category', 'brand',
                        'is_active', 'created_at', 'updated_at',
                    ],
                ],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);
    }

    public function test_can_create_product(): void
    {
        $category = Category::where('organization_id', $this->orgId)->first();
        $brand = Brand::where('organization_id', $this->orgId)->first();

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/products', [
                'sku' => 'BEV-SPRITE-01',
                'name' => 'Sprite Lemon Lime',
                'barcode' => '885000000099',
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'is_active' => true,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'sku' => 'BEV-SPRITE-01',
                    'name' => 'Sprite Lemon Lime',
                ],
            ]);

        $this->assertDatabaseHas('products', [
            'organization_id' => $this->orgId,
            'sku' => 'BEV-SPRITE-01',
        ]);
    }

    public function test_duplicate_sku_in_same_organization_is_rejected(): void
    {
        $category = Category::where('organization_id', $this->orgId)->first();

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/products', [
                'sku' => 'BEV-COCA', // already seeded
                'name' => 'Duplicate Coca',
                'category_id' => $category->id,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    public function test_cannot_delete_product_with_associated_goods(): void
    {
        $product = Product::where('organization_id', $this->orgId)
            ->where('sku', 'BEV-COCA')
            ->first();

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/v1/products/' . $product->id);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'code' => 'RESOURCE_IN_USE',
            ]);
    }
}
