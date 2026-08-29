<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\AuthenticationAudit\Models\User;
use Tests\TestCase;

class OrganizationContextTest extends TestCase
{
    use DatabaseTransactions;
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function getAuthToken(string $email = 'owner@test.com'): string
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'password123',
        ]);

        return $response->json('data.access_token');
    }

    public function test_user_can_list_their_organizations(): void
    {
        $token = $this->getAuthToken('owner@test.com');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/organizations');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonFragment([
                'name' => 'Global Supply Co.',
                'code' => 'OWNER',
            ]);
    }

    public function test_missing_organization_header_returns_422(): void
    {
        $token = $this->getAuthToken('owner@test.com');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/organizations/current');

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'ORGANIZATION_REQUIRED',
            ]);
    }

    public function test_nonexistent_organization_uuid_returns_404(): void
    {
        $token = $this->getAuthToken('owner@test.com');
        $randomUuid = (string) Str::uuid();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Organization-Id' => $randomUuid,
        ])->getJson('/api/v1/organizations/current');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code' => 'ORGANIZATION_NOT_FOUND',
            ]);
    }

    public function test_valid_organization_context_resolves_organization_and_role(): void
    {
        $token = $this->getAuthToken('staff@test.com');
        $org = Organization::where('name', 'Global Supply Co.')->first();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Organization-Id' => $org->id,
        ])->getJson('/api/v1/organizations/current');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'organization' => [
                        'id' => $org->id,
                        'name' => 'Global Supply Co.',
                    ],
                    'role' => [
                        'code' => 'STAFF',
                    ],
                    'user' => [
                        'email' => 'staff@test.com',
                    ],
                ],
            ]);
    }
}
