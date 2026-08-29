<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\AuthenticationAudit\Models\AuditLog;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\AuthenticationAudit\Models\User;
use Tests\TestCase;

class AuditLogWebTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $staff;
    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@test.com')->first();
        $this->staff = User::where('email', 'staff@test.com')->first();
        $this->org = Organization::where('name', 'Global Supply Co.')->first();
    }

    public function test_admin_can_view_audit_logs(): void
    {
        $log = AuditLog::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->admin->id,
            'action' => 'TEST_AUDIT_EVENT',
            'entity_type' => 'StockDocument',
            'entity_id' => (string) \Illuminate\Support\Str::uuid(),
            'old_data' => ['status' => 'DRAFT'],
            'new_data' => ['status' => 'PENDING'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);

        $this->actingAs($this->admin)->withSession(['current_organization_id' => $this->org->id])
            ->get('/audit-logs')
            ->assertStatus(200)
            ->assertSee('System Audit Trail')
            ->assertSee('TEST_AUDIT_EVENT');

        $this->actingAs($this->admin)->withSession(['current_organization_id' => $this->org->id])
            ->get("/audit-logs/{$log->id}")
            ->assertStatus(200)
            ->assertSee('TEST_AUDIT_EVENT')
            ->assertSee('Pre-Mutation State')
            ->assertSee('Post-Mutation State');
    }

    public function test_staff_cannot_view_audit_logs(): void
    {
        $this->actingAs($this->staff)->withSession(['current_organization_id' => $this->org->id])
            ->get('/audit-logs')
            ->assertStatus(403);
    }
}
