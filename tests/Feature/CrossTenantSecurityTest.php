<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\AuthenticationAudit\Enums\RoleEnum;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\AuthenticationAudit\Models\Role;
use Modules\AuthenticationAudit\Models\User;
use Modules\AuthenticationAudit\Models\UserOrganization;
use Tests\TestCase;

class CrossTenantSecurityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_user_cannot_access_organization_they_are_not_a_member_of(): void
    {
        // 1. Create Organization B with Owner B
        $userB = User::firstOrCreate(
            ['email' => 'owner_b@test.com'],
            [
                'id' => (string) Str::uuid(),
                'username' => 'owner_b',
                'password' => Hash::make('password123'),
                'is_active' => true,
                'is_verified' => true,
            ]
        );

        $orgB = Organization::firstOrCreate(
            ['name' => 'Company B Corp'],
            [
                'id' => (string) Str::uuid(),
                'created_by' => $userB->id,
            ]
        );

        $ownerRole = Role::where('code', RoleEnum::OWNER->value)->first();

        UserOrganization::firstOrCreate(
            [
                'user_id' => $userB->id,
                'organization_id' => $orgB->id,
            ],
            [
                'role_id' => $ownerRole->id,
            ]
        );

        // 2. Login User A (Member of Global Supply Co, NOT Company B)
        $loginA = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@test.com',
            'password' => 'password123',
        ]);

        $tokenA = $loginA->json('data.access_token');

        // 3. User A attempts to set X-Organization-Id to Organization B
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $tokenA,
            'X-Organization-Id' => $orgB->id,
        ])->getJson('/api/v1/organizations/current');

        // 4. MUST be rejected with 403 ORGANIZATION_ACCESS_DENIED
        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'ORGANIZATION_ACCESS_DENIED',
            ]);
    }
}
