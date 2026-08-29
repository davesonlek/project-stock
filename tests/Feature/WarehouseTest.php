<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\AuthenticationAudit\Models\User;
use Modules\MasterData\Models\Warehouse;
use Tests\TestCase;

class WarehouseTest extends TestCase
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

    public function test_can_create_warehouse_with_valid_manager(): void
    {
        $manager = User::where('email', 'manager@test.com')->first();

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/warehouses', [
                'code' => 'DRY-01',
                'name' => 'Dry Storage Warehouse',
                'address' => '104 Logistics Blvd',
                'manager_id' => $manager->id,
                'is_active' => true,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'code' => 'DRY-01',
                    'name' => 'Dry Storage Warehouse',
                ],
            ]);
    }

    public function test_cannot_assign_non_member_user_as_warehouse_manager(): void
    {
        $foreignUser = User::create([
            'id' => (string) Str::uuid(),
            'email' => 'external_user@test.com',
            'username' => 'external_user',
            'password' => 'password',
            'is_active' => true,
            'is_verified' => true,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/warehouses', [
                'code' => 'DRY-02',
                'name' => 'Dry Storage Warehouse 2',
                'manager_id' => $foreignUser->id,
            ]);

        $response->assertStatus(422);
    }
}
