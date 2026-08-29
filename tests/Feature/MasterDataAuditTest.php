<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\AuthenticationAudit\Models\AuditLog;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\MasterData\Models\Category;
use Modules\MasterData\Models\Product;
use Tests\TestCase;

class MasterDataAuditTest extends TestCase
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

    public function test_creating_and_updating_product_records_audit_trail(): void
    {
        $category = Category::where('organization_id', $this->orgId)->first();

        // 1. Create Product
        $createResponse = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/products', [
                'sku' => 'AUDIT-PROD-01',
                'name' => 'Audit Test Product',
                'category_id' => $category->id,
            ]);

        $createResponse->assertStatus(201);
        $productId = (string) $createResponse->json('data.id');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'PRODUCT_CREATED',
            'entity_type' => 'Product',
            'entity_id' => $productId,
            'organization_id' => $this->orgId,
        ]);

        // 2. Update Product
        $updateResponse = $this->withHeaders($this->authHeaders())
            ->putJson('/api/v1/products/' . $productId, [
                'name' => 'Audit Test Product Updated',
            ]);

        $updateResponse->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'PRODUCT_UPDATED',
            'entity_type' => 'Product',
            'entity_id' => $productId,
            'organization_id' => $this->orgId,
        ]);
    }
}
