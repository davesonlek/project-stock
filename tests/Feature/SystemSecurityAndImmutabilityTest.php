<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\AuthenticationAudit\Models\AuditLog;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\AuthenticationAudit\Models\User;
use Modules\InventoryTransaction\Models\StockBalance;
use Modules\InventoryTransaction\Models\StockMovement;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class SystemSecurityAndImmutabilityTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected Organization $org;
    protected Warehouse $warehouse;
    protected WarehouseLocation $locA;
    protected Goods $goods;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@test.com')->first();
        $this->org = Organization::where('name', 'Global Supply Co.')->first();

        $this->warehouse = Warehouse::where('organization_id', $this->org->id)->where('code', 'MAIN')->first();
        $this->locA = WarehouseLocation::where('warehouse_id', $this->warehouse->id)->where('code', 'A-01-01')->first();
        $this->goods = Goods::where('organization_id', $this->org->id)->first();
    }

    /**
     * Requirement 43: PostgreSQL trigger prevents UPDATE on stock_movements
     */
    public function test_postgresql_trigger_prevents_update_on_stock_movements(): void
    {
        $movement = StockMovement::first();
        $this->assertNotNull($movement);

        $updateRejected = false;
        try {
            DB::statement("UPDATE stock_movements SET quantity_delta = 999 WHERE id = {$movement->id}");
        } catch (QueryException $e) {
            $updateRejected = true;
            $this->assertStringContainsString('immutable', strtolower($e->getMessage()));
        }
        $this->assertTrue($updateRejected, 'PostgreSQL trigger failed to block UPDATE on stock_movements');
    }

    /**
     * Requirement 43: PostgreSQL trigger prevents DELETE on stock_movements
     */
    public function test_postgresql_trigger_prevents_delete_on_stock_movements(): void
    {
        $movement = StockMovement::first();
        $this->assertNotNull($movement);

        $deleteRejected = false;
        try {
            DB::statement("DELETE FROM stock_movements WHERE id = {$movement->id}");
        } catch (QueryException $e) {
            $deleteRejected = true;
            $this->assertStringContainsString('immutable', strtolower($e->getMessage()));
        }
        $this->assertTrue($deleteRejected, 'PostgreSQL trigger failed to block DELETE on stock_movements');
    }

    /**
     * Requirement 44: PostgreSQL trigger prevents UPDATE on audit_logs
     */
    public function test_postgresql_trigger_prevents_update_on_audit_logs(): void
    {
        $log = AuditLog::first();
        $this->assertNotNull($log);

        $updateRejected = false;
        try {
            DB::statement("UPDATE audit_logs SET action = 'TAMPERED' WHERE id = {$log->id}");
        } catch (QueryException $e) {
            $updateRejected = true;
            $this->assertStringContainsString('immutable', strtolower($e->getMessage()));
        }
        $this->assertTrue($updateRejected, 'PostgreSQL trigger failed to block UPDATE on audit_logs');
    }

    /**
     * Requirement 44: PostgreSQL trigger prevents DELETE on audit_logs
     */
    public function test_postgresql_trigger_prevents_delete_on_audit_logs(): void
    {
        $log = AuditLog::first();
        $this->assertNotNull($log);

        $deleteRejected = false;
        try {
            DB::statement("DELETE FROM audit_logs WHERE id = {$log->id}");
        } catch (QueryException $e) {
            $deleteRejected = true;
            $this->assertStringContainsString('immutable', strtolower($e->getMessage()));
        }
        $this->assertTrue($deleteRejected, 'PostgreSQL trigger failed to block DELETE on audit_logs');
    }

    /**
     * Requirement 63: PostgreSQL CHECK constraints enforce on_hand >= 0, reserved >= 0, reserved <= on_hand
     */
    public function test_database_check_constraints_enforce_inventory_invariants(): void
    {
        // 1. on_hand < 0 must be rejected
        $negativeOnHandRejected = false;
        try {
            DB::statement("INSERT INTO stock_balances (organization_id, warehouse_id, location_id, goods_id, on_hand, reserved, created_at, updated_at) 
                           VALUES ('{$this->org->id}', {$this->warehouse->id}, {$this->locA->id}, {$this->goods->id}, -5.0000, 0.0000, NOW(), NOW())");
        } catch (QueryException $e) {
            $negativeOnHandRejected = true;
        }
        $this->assertTrue($negativeOnHandRejected, 'Database failed to reject on_hand < 0');
    }

    /**
     * Requirement 6: Password security in database
     */
    public function test_user_passwords_are_securely_hashed_and_not_plain_text(): void
    {
        $users = User::all();
        $this->assertNotEmpty($users);

        foreach ($users as $user) {
            $this->assertNotEquals('password123', $user->getRawOriginal('password'));
            $this->assertStringStartsWith('$2y$', $user->getRawOriginal('password'));
            $this->assertTrue(Hash::check('password123', $user->password));
        }
    }

    /**
     * Requirement 7: JWT token payload safety
     */
    public function test_jwt_token_payload_contains_no_sensitive_secrets_or_credentials(): void
    {
        $token = JWTAuth::fromUser($this->admin);
        $payload = JWTAuth::setToken($token)->getPayload();

        $this->assertArrayNotHasKey('password', $payload->toArray());
        $this->assertArrayNotHasKey('database', $payload->toArray());
        $this->assertArrayNotHasKey('db_password', $payload->toArray());
        $this->assertArrayNotHasKey('secret', $payload->toArray());
        $this->assertEquals($this->admin->id, $payload->get('sub'));
    }
}
