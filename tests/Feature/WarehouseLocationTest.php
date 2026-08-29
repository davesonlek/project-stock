<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;
use Tests\TestCase;

class WarehouseLocationTest extends TestCase
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

    public function test_can_create_location_and_query_nested_warehouse_locations(): void
    {
        $warehouse = Warehouse::where('organization_id', $this->orgId)->where('code', 'MAIN')->first();

        // 1. Create Location
        $createResponse = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/warehouse-locations', [
                'warehouse_id' => $warehouse->id,
                'code' => 'Z-99-99',
                'name' => 'Special Buffer Bay',
                'zone' => 'Zone Z',
                'aisle' => '99',
                'rack' => '99',
                'bin' => '99',
                'is_active' => true,
            ]);

        $createResponse->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'code' => 'Z-99-99',
                ],
            ]);

        // 2. Query Nested Endpoint
        $nestedResponse = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/warehouses/' . $warehouse->id . '/locations');

        $nestedResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonFragment([
                'code' => 'Z-99-99',
            ]);
    }

    public function test_duplicate_code_in_same_warehouse_is_rejected(): void
    {
        $warehouse = Warehouse::where('organization_id', $this->orgId)->where('code', 'MAIN')->first();

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/warehouse-locations', [
                'warehouse_id' => $warehouse->id,
                'code' => 'A-01-01', // already seeded in MAIN
            ]);

        $response->assertStatus(422);
    }
}
