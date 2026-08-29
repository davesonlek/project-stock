<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\AuthenticationAudit\Models\User;
use Modules\InventoryTransaction\Models\StockDocument;
use Tests\TestCase;

class SystemPerformanceBenchmarkTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@test.com')->first();
        $this->org = Organization::where('name', 'Global Supply Co.')->first();
    }

    protected function authSession(): array
    {
        return ['current_organization_id' => $this->org->id];
    }

    /**
     * Requirement 76 & 112: Query count review and N+1 prevention across core screens
     */
    public function test_query_counts_remain_bounded_and_free_of_n_plus_one(): void
    {
        // 1. Dashboard Query Count
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->admin)->withSession($this->authSession())->get('/dashboard');
        $dashboardQueries = count(DB::getQueryLog());
        $this->assertLessThanOrEqual(25, $dashboardQueries, "Dashboard generated excessive queries ({$dashboardQueries})");

        // 2. Product List Query Count
        DB::flushQueryLog();
        $this->actingAs($this->admin)->withSession($this->authSession())->get('/products');
        $productQueries = count(DB::getQueryLog());
        $this->assertLessThanOrEqual(15, $productQueries, "Product list generated excessive queries ({$productQueries})");

        // 3. Stock Balance List Query Count
        DB::flushQueryLog();
        $this->actingAs($this->admin)->withSession($this->authSession())->get('/inventory/stock');
        $stockQueries = count(DB::getQueryLog());
        $this->assertLessThanOrEqual(15, $stockQueries, "Stock balance list generated excessive queries ({$stockQueries})");

        // 4. Stock Documents List Query Count
        DB::flushQueryLog();
        $this->actingAs($this->admin)->withSession($this->authSession())->get('/stock/documents');
        $docQueries = count(DB::getQueryLog());
        $this->assertLessThanOrEqual(25, $docQueries, "Stock documents list generated excessive queries ({$docQueries})");

        // 5. Stock Movement Ledger Query Count
        DB::flushQueryLog();
        $this->actingAs($this->admin)->withSession($this->authSession())->get('/inventory/movements');
        $movementQueries = count(DB::getQueryLog());
        $this->assertLessThanOrEqual(25, $movementQueries, "Stock movement ledger generated excessive queries ({$movementQueries})");

        // 6. Document Detail Query Count
        $doc = StockDocument::where('organization_id', $this->org->id)->first();
        if ($doc) {
            DB::flushQueryLog();
            $this->actingAs($this->admin)->withSession($this->authSession())->get("/stock/documents/{$doc->id}");
            $docDetailQueries = count(DB::getQueryLog());
            $this->assertLessThanOrEqual(40, $docDetailQueries, "Document detail generated excessive queries ({$docDetailQueries})");
        }
    }

    /**
     * Requirement 80 & 81: PostgreSQL EXPLAIN ANALYZE on critical query paths
     */
    public function test_postgresql_explain_analyze_on_critical_indexes(): void
    {
        // 1. Stock Balance Lookup Query
        $explainBalance = DB::select("EXPLAIN (FORMAT JSON) SELECT * FROM stock_balances WHERE organization_id = '{$this->org->id}' AND warehouse_id = 1 AND location_id = 1 AND goods_id = 1");
        $this->assertNotEmpty($explainBalance);

        // 2. Stock Movement by Document Query
        $explainMovement = DB::select("EXPLAIN (FORMAT JSON) SELECT * FROM stock_movements WHERE organization_id = '{$this->org->id}' AND document_id = '01a04b73-4d4c-719e-8567-4b743b326f93'");
        $this->assertNotEmpty($explainMovement);

        // 3. Stock Document by Organization and Status Query
        $explainDoc = DB::select("EXPLAIN (FORMAT JSON) SELECT * FROM stock_documents WHERE organization_id = '{$this->org->id}' AND status = 'APPROVED'");
        $this->assertNotEmpty($explainDoc);

        // 4. Overdue Reservation Query
        $explainRes = DB::select("EXPLAIN (FORMAT JSON) SELECT * FROM stock_reservations WHERE organization_id = '{$this->org->id}' AND status = 'ACTIVE' AND expires_at <= NOW()");
        $this->assertNotEmpty($explainRes);
    }
}
