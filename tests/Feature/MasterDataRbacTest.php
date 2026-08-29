<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\MasterData\Models\Category;
use Modules\MasterData\Models\Product;
use Tests\TestCase;

class MasterDataRbacTest extends TestCase
{
    use DatabaseTransactions;

    protected string $orgId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $org = Organization::where('name', 'Global Supply Co.')->first();
        $this->orgId = $org->id;
    }

    private function getAuthToken(string $email): string
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'password123',
        ]);

        return $response->json('data.access_token');
    }

    public function test_staff_can_view_but_cannot_create_or_update_or_delete_product(): void
    {
        $token = $this->getAuthToken('staff@test.com');
        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'X-Organization-Id' => $this->orgId,
        ];

        // 1. View list -> allowed
        $viewResponse = $this->withHeaders($headers)->getJson('/api/v1/products');
        $viewResponse->assertStatus(200);

        // 2. Create -> forbidden 403
        $category = Category::where('organization_id', $this->orgId)->first();
        $createResponse = $this->withHeaders($headers)->postJson('/api/v1/products', [
            'sku' => 'STAFF-SKU',
            'name' => 'Staff Product',
            'category_id' => $category->id,
        ]);
        $createResponse->assertStatus(403);

        // 3. Update -> forbidden 403
        $product = Product::where('organization_id', $this->orgId)->first();
        $updateResponse = $this->withHeaders($headers)->putJson('/api/v1/products/' . $product->id, [
            'name' => 'Modified by Staff',
        ]);
        $updateResponse->assertStatus(403);

        // 4. Delete -> forbidden 403
        $deleteResponse = $this->withHeaders($headers)->deleteJson('/api/v1/products/' . $product->id);
        $deleteResponse->assertStatus(403);
    }

    public function test_manager_can_create_and_update_but_cannot_delete_product(): void
    {
        $token = $this->getAuthToken('manager@test.com');
        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'X-Organization-Id' => $this->orgId,
        ];

        // 1. Create -> allowed
        $category = Category::where('organization_id', $this->orgId)->first();
        $createResponse = $this->withHeaders($headers)->postJson('/api/v1/products', [
            'sku' => 'MGR-SKU-01',
            'name' => 'Manager Product',
            'category_id' => $category->id,
        ]);
        $createResponse->assertStatus(201);
        $createdId = $createResponse->json('data.id');

        // 2. Update -> allowed
        $updateResponse = $this->withHeaders($headers)->putJson('/api/v1/products/' . $createdId, [
            'name' => 'Manager Product Updated',
        ]);
        $updateResponse->assertStatus(200);

        // 3. Delete -> forbidden 403 (Physical delete is restricted to Owner/Admin)
        $deleteResponse = $this->withHeaders($headers)->deleteJson('/api/v1/products/' . $createdId);
        $deleteResponse->assertStatus(403);
    }
}
