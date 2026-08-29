<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\AuthenticationAudit\Models\AuditLog;
use Modules\AuthenticationAudit\Models\User;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use DatabaseTransactions;
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_valid_login_returns_jwt_token_and_user_data(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'token_type' => 'bearer',
                    'user' => [
                        'email' => 'owner@test.com',
                        'username' => 'owner',
                        'is_active' => true,
                    ],
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'access_token',
                    'token_type',
                    'expires_in',
                    'user' => [
                        'id',
                        'email',
                        'username',
                        'is_active',
                        'is_verified',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'LOGIN_SUCCESS',
        ]);
    }

    public function test_invalid_email_returns_401(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nonexistent@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'INVALID_CREDENTIALS',
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'LOGIN_FAILED',
        ]);
    }

    public function test_invalid_password_returns_401(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@test.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'INVALID_CREDENTIALS',
            ]);
    }

    public function test_inactive_account_login_is_blocked_with_403(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'inactive@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'ACCOUNT_INACTIVE',
            ]);
    }

    public function test_me_endpoint_returns_authenticated_user(): void
    {
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@test.com',
            'password' => 'password123',
        ]);

        $token = $login->json('data.access_token');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'email' => 'admin@test.com',
                    'username' => 'admin',
                ],
            ]);
    }

    public function test_unauthenticated_request_to_protected_route_returns_401(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHENTICATED',
            ]);
    }

    public function test_refresh_token_issues_new_token(): void
    {
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'manager@test.com',
            'password' => 'password123',
        ]);

        $token = $login->json('data.access_token');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/refresh');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'access_token',
                    'token_type',
                    'expires_in',
                ],
            ]);
    }

    public function test_logout_invalidates_token(): void
    {
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'staff@test.com',
            'password' => 'password123',
        ]);

        $token = $login->json('data.access_token');

        $logoutResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/logout');

        $logoutResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Trying to use the invalidated token should fail
        $meResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/auth/me');

        $meResponse->assertStatus(401);
    }
}
