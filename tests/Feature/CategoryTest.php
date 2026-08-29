<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\AuthenticationAudit\Models\User;
use Modules\MasterData\Models\Category;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use DatabaseTransactions;

    protected string $token;
    protected string $orgId;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->user = User::where('email', 'owner@test.com')->first();
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

    public function test_can_list_categories_with_pagination_and_search(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/categories?search=bev&per_page=10');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'is_active', 'created_at', 'updated_at'],
                ],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);
    }

    public function test_can_create_category(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/categories', [
                'name' => 'Snacks & Confectionery',
                'is_active' => true,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Snacks & Confectionery',
                    'is_active' => true,
                ],
            ]);

        $this->assertDatabaseHas('categories', [
            'organization_id' => $this->orgId,
            'name' => 'Snacks & Confectionery',
        ]);
    }

    public function test_duplicate_category_name_in_same_organization_is_rejected(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/categories', [
                'name' => 'Beverages', // already seeded
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    public function test_can_show_category(): void
    {
        $category = Category::where('organization_id', $this->orgId)->first();

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/categories/' . $category->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $category->id,
                    'name' => $category->name,
                ],
            ]);
    }

    public function test_can_update_category(): void
    {
        $category = Category::where('organization_id', $this->orgId)->first();

        $response = $this->withHeaders($this->authHeaders())
            ->putJson('/api/v1/categories/' . $category->id, [
                'name' => 'Updated Beverage Category',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Updated Beverage Category',
                ],
            ]);
    }

    public function test_can_activate_and_deactivate_category(): void
    {
        $category = Category::where('organization_id', $this->orgId)->first();

        $deactResponse = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/categories/' . $category->id . '/deactivate');

        $deactResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => ['is_active' => false],
            ]);

        $actResponse = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/categories/' . $category->id . '/activate');

        $actResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => ['is_active' => true],
            ]);
    }

    public function test_cannot_delete_category_with_existing_products(): void
    {
        $category = Category::where('organization_id', $this->orgId)
            ->where('name', 'Beverages')
            ->first();

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/v1/categories/' . $category->id);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'code' => 'RESOURCE_IN_USE',
            ]);
    }
}
