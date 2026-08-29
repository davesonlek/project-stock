<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\AuthenticationAudit\Models\Role;
use Modules\AuthenticationAudit\Models\User;
use Modules\AuthenticationAudit\Models\UserOrganization;
use Modules\AuthenticationAudit\Policies\AuditLogPolicy;
use Modules\AuthenticationAudit\Policies\OrganizationPolicy;
use Modules\AuthenticationAudit\Services\OrganizationContext;
use Modules\InventoryTransaction\Policies\StockDocumentPolicy;
use Modules\MasterData\Policies\MasterDataPolicy;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use DatabaseTransactions;
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function setContextForUser(string $email): User
    {
        $user = User::where('email', $email)->first();
        $org = Organization::where('name', 'Global Supply Co.')->first();
        $membership = UserOrganization::where('user_id', $user->id)
            ->where('organization_id', $org->id)
            ->with('role')
            ->first();

        $context = new OrganizationContext(
            user: $user,
            organization: $org,
            membership: $membership,
            role: $membership->role
        );

        app()->instance(OrganizationContext::class, $context);

        return $user;
    }

    public function test_staff_permissions_matrix(): void
    {
        $user = $this->setContextForUser('staff@test.com');

        $masterPolicy = new MasterDataPolicy();
        $this->assertTrue($masterPolicy->view($user), 'Staff should be allowed to view master data');
        $this->assertFalse($masterPolicy->create($user), 'Staff should NOT be allowed to create master data');
        $this->assertFalse($masterPolicy->delete($user), 'Staff should NOT be allowed to delete master data');

        $stockPolicy = new StockDocumentPolicy();
        $this->assertTrue($stockPolicy->view($user), 'Staff should be allowed to view stock documents');
        $this->assertTrue($stockPolicy->create($user), 'Staff should be allowed to create stock documents');
        $this->assertTrue($stockPolicy->submit($user), 'Staff should be allowed to submit stock documents');
        $this->assertFalse($stockPolicy->approve($user), 'Staff should NOT be allowed to approve stock documents');
        $this->assertFalse($stockPolicy->post($user), 'Staff should NOT be allowed to post stock documents');
        $this->assertFalse($stockPolicy->reverse($user), 'Staff should NOT be allowed to reverse stock documents');

        $auditPolicy = new AuditLogPolicy();
        $this->assertFalse($auditPolicy->view($user), 'Staff should NOT be allowed to view audit logs');
    }

    public function test_manager_permissions_matrix(): void
    {
        $user = $this->setContextForUser('manager@test.com');

        $masterPolicy = new MasterDataPolicy();
        $this->assertTrue($masterPolicy->view($user));
        $this->assertTrue($masterPolicy->create($user), 'Manager should be allowed to create master data');
        $this->assertTrue($masterPolicy->update($user), 'Manager should be allowed to update master data');
        $this->assertFalse($masterPolicy->delete($user), 'Manager should NOT be allowed to delete master data');

        $stockPolicy = new StockDocumentPolicy();
        $this->assertTrue($stockPolicy->approve($user), 'Manager should be allowed to approve stock documents');
        $this->assertTrue($stockPolicy->post($user), 'Manager should be allowed to post stock documents');
        $this->assertFalse($stockPolicy->reverse($user), 'Manager should NOT be allowed to reverse stock documents');

        $auditPolicy = new AuditLogPolicy();
        $this->assertTrue($auditPolicy->view($user), 'Manager should be allowed to view audit logs');
    }

    public function test_admin_permissions_matrix(): void
    {
        $user = $this->setContextForUser('admin@test.com');

        $masterPolicy = new MasterDataPolicy();
        $this->assertTrue($masterPolicy->delete($user), 'Admin should be allowed to delete master data');

        $stockPolicy = new StockDocumentPolicy();
        $this->assertTrue($stockPolicy->approve($user));
        $this->assertTrue($stockPolicy->post($user));
        $this->assertTrue($stockPolicy->reverse($user), 'Admin should be allowed to reverse stock documents');

        $orgPolicy = new OrganizationPolicy();
        $this->assertTrue($orgPolicy->update($user), 'Admin should be allowed to update organization');
        $this->assertTrue($orgPolicy->manageUsers($user), 'Admin should be allowed to manage users');
    }

    public function test_owner_permissions_matrix(): void
    {
        $user = $this->setContextForUser('owner@test.com');

        $masterPolicy = new MasterDataPolicy();
        $this->assertTrue($masterPolicy->view($user));
        $this->assertTrue($masterPolicy->create($user));
        $this->assertTrue($masterPolicy->update($user));
        $this->assertTrue($masterPolicy->delete($user));

        $stockPolicy = new StockDocumentPolicy();
        $this->assertTrue($stockPolicy->create($user));
        $this->assertTrue($stockPolicy->approve($user));
        $this->assertTrue($stockPolicy->post($user));
        $this->assertTrue($stockPolicy->reverse($user));

        $orgPolicy = new OrganizationPolicy();
        $this->assertTrue($orgPolicy->update($user));
        $this->assertTrue($orgPolicy->manageUsers($user));

        $auditPolicy = new AuditLogPolicy();
        $this->assertTrue($auditPolicy->view($user));
    }
}
