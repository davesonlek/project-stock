<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\MasterData\Models\Supplier;
use Tests\TestCase;

class SupplierTest extends TestCase
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

    public function test_can_create_and_list_suppliers(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/suppliers', [
                'name' => 'Prime Distribution Co',
                'tax_id' => 'TAX-PRIME-001',
                'contact_person' => 'Jane Smith',
                'email' => 'jane@prime.com',
                'phone' => '0812345678',
                'is_active' => true,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Prime Distribution Co',
                    'tax_id' => 'TAX-PRIME-001',
                ],
            ]);
    }

    public function test_duplicate_tax_id_in_same_organization_is_rejected(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/suppliers', [
                'name' => 'Duplicate Tax Supplier',
                'tax_id' => 'TAX-BEV-001', // Already seeded
            ]);

        $response->assertStatus(422);
    }
}
