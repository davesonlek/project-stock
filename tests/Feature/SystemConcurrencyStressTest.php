<?php

namespace Tests\Feature;

use Modules\AuthenticationAudit\Models\Organization;
use Modules\AuthenticationAudit\Models\User;
use Modules\InventoryTransaction\Enums\StockDocumentStatus;
use Modules\InventoryTransaction\Enums\StockMovementType;
use Modules\InventoryTransaction\Models\StockBalance;
use Modules\InventoryTransaction\Models\StockDocument;
use Modules\InventoryTransaction\Models\StockMovement;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Product;
use Modules\MasterData\Models\Unit;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;
use Tests\TestCase;

class SystemConcurrencyStressTest extends TestCase
{
    protected string $token;
    protected string $orgId;
    protected string $userId;
    protected Warehouse $warehouse;
    protected WarehouseLocation $locA;
    protected WarehouseLocation $locB;
    protected Product $product;
    protected Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $org = Organization::where('name', 'Global Supply Co.')->first();
        $this->orgId = $org->id;

        $user = User::where('email', 'admin@test.com')->first();
        $this->userId = $user->id;

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@test.com',
            'password' => 'password123',
        ]);
        $this->token = $login->json('data.access_token');

        $this->warehouse = Warehouse::where('organization_id', $this->orgId)->where('code', 'MAIN')->first();
        $this->locA = WarehouseLocation::where('warehouse_id', $this->warehouse->id)->where('code', 'A-01-01')->first();
        $this->locB = WarehouseLocation::where('warehouse_id', $this->warehouse->id)->where('code', 'A-01-02')->first();

        $this->product = Product::where('organization_id', $this->orgId)->first();
        $this->unit = Unit::where('organization_id', $this->orgId)->first();
    }

    protected function authHeaders(string $idempotencyKey): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'X-Organization-Id' => $this->orgId,
            'Idempotency-Key' => $idempotencyKey,
        ];
    }

    protected function createIsolatedGoods(): Goods
    {
        $unique = uniqid();
        return Goods::create([
            'organization_id' => $this->orgId,
            'product_id' => $this->product->id,
            'unit_id' => $this->unit->id,
            'sku' => "CONC-STRESS-{$unique}",
            'name' => "Stress Goods {$unique}",
            'pack_size' => 1,
            'is_lot_tracked' => false,
            'is_serial_tracked' => false,
            'is_active' => true,
        ]);
    }

    protected function spawnWorker(string $documentId, string $key): array
    {
        $phpBinary = PHP_BINARY;
        $artisanPath = base_path('artisan');
        $cmd = "\"{$phpBinary}\" \"{$artisanPath}\" stock:post-worker \"{$documentId}\" \"{$key}\" \"{$this->userId}\" \"{$this->orgId}\"";

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptors, $pipes, base_path());

        return [
            'process' => $process,
            'pipes' => $pipes,
        ];
    }

    protected function collectWorkerOutput(array $worker): array
    {
        $stdout = stream_get_contents($worker['pipes'][1]);
        $stderr = stream_get_contents($worker['pipes'][2]);

        fclose($worker['pipes'][0]);
        fclose($worker['pipes'][1]);
        fclose($worker['pipes'][2]);

        $exitCode = proc_close($worker['process']);
        $decoded = json_decode(trim($stdout), true);

        return [
            'exit_code' => $exitCode,
            'stdout' => $stdout,
            'stderr' => $stderr,
            'data' => $decoded ?? ['success' => false, 'code' => 'PARSE_ERROR', 'raw' => $stdout, 'stderr' => $stderr],
        ];
    }

    /**
     * Requirement 45, 98, 140: Primary Race Condition Proof (Stock=10, Two Issue 8 -> 1 Success, 1 Failure, Final=2)
     */
    public function test_primary_critical_race_condition_proof(): void
    {
        $goods = $this->createIsolatedGoods();

        // 1. Initial State: Stock = 10
        StockBalance::create([
            'organization_id' => $this->orgId,
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->locA->id,
            'goods_id' => $goods->id,
            'on_hand' => '10.0000',
            'reserved' => '0.0000',
        ]);

        // 2. Prepare Document A (ISSUE 8) -> APPROVED
        $docA = $this->withHeaders($this->authHeaders('STRESS-DOC-A01-' . uniqid()))->postJson('/api/v1/stock/documents', [
            'document_type' => 'ISSUE',
            'source_warehouse_id' => $this->warehouse->id,
            'source_location_id' => $this->locA->id,
        ])->json('data.id');
        $this->withHeaders($this->authHeaders('STRESS-DOC-A02-' . uniqid()))->postJson("/api/v1/stock/documents/{$docA}/lines", ['goods_id' => $goods->id, 'quantity' => 8]);
        $this->withHeaders($this->authHeaders('STRESS-DOC-A03-' . uniqid()))->postJson("/api/v1/stock/documents/{$docA}/submit");
        $this->withHeaders($this->authHeaders('STRESS-DOC-A04-' . uniqid()))->postJson("/api/v1/stock/documents/{$docA}/approve");

        // 3. Prepare Document B (ISSUE 8) -> APPROVED
        $docB = $this->withHeaders($this->authHeaders('STRESS-DOC-B01-' . uniqid()))->postJson('/api/v1/stock/documents', [
            'document_type' => 'ISSUE',
            'source_warehouse_id' => $this->warehouse->id,
            'source_location_id' => $this->locA->id,
        ])->json('data.id');
        $this->withHeaders($this->authHeaders('STRESS-DOC-B02-' . uniqid()))->postJson("/api/v1/stock/documents/{$docB}/lines", ['goods_id' => $goods->id, 'quantity' => 8]);
        $this->withHeaders($this->authHeaders('STRESS-DOC-B03-' . uniqid()))->postJson("/api/v1/stock/documents/{$docB}/submit");
        $this->withHeaders($this->authHeaders('STRESS-DOC-B04-' . uniqid()))->postJson("/api/v1/stock/documents/{$docB}/approve");

        // 4. Spawn Both Processes Concurrently
        $workerA = $this->spawnWorker($docA, 'STRESS-KEY-A-' . uniqid());
        $workerB = $this->spawnWorker($docB, 'STRESS-KEY-B-' . uniqid());

        // 5. Collect Results
        $resA = $this->collectWorkerOutput($workerA);
        $resB = $this->collectWorkerOutput($workerB);

        $codes = [$resA['data']['code'] ?? 'UNKNOWN', $resB['data']['code'] ?? 'UNKNOWN'];

        // Assert: Exactly ONE is SUCCESS, exactly ONE is INSUFFICIENT_AVAILABLE_STOCK
        $this->assertContains('SUCCESS', $codes);
        $this->assertContains('INSUFFICIENT_AVAILABLE_STOCK', $codes);

        // 6. Verify Final Database State: Stock must be 2.0000
        $finalBalance = StockBalance::where('organization_id', $this->orgId)
            ->where('warehouse_id', $this->warehouse->id)
            ->where('location_id', $this->locA->id)
            ->where('goods_id', $goods->id)
            ->value('on_hand');

        $this->assertEquals('2.0000', $finalBalance);

        // 7. Verify Total Movements = -8 (only 1 issue recorded)
        $movements = StockMovement::whereIn('document_id', [$docA, $docB])->get();
        $this->assertCount(1, $movements);
        $this->assertEquals('-8.0000', $movements->first()->quantity_delta);
    }

    /**
     * Requirement 100, 143: Zero-sum conservation across concurrent bidirectional transfers
     */
    public function test_concurrent_transfers_conserve_total_system_stock(): void
    {
        $goods = $this->createIsolatedGoods();

        // 1. Initial State: LocA = 100, LocB = 100 (Total = 200)
        StockBalance::create([
            'organization_id' => $this->orgId,
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->locA->id,
            'goods_id' => $goods->id,
            'on_hand' => '100.0000',
            'reserved' => '0.0000',
        ]);
        StockBalance::create([
            'organization_id' => $this->orgId,
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->locB->id,
            'goods_id' => $goods->id,
            'on_hand' => '100.0000',
            'reserved' => '0.0000',
        ]);

        // Doc 1: A -> B 30
        $doc1 = $this->withHeaders($this->authHeaders('TR-AB-01-' . uniqid()))->postJson('/api/v1/stock/documents', [
            'document_type' => 'TRANSFER',
            'source_warehouse_id' => $this->warehouse->id,
            'source_location_id' => $this->locA->id,
            'destination_warehouse_id' => $this->warehouse->id,
            'destination_location_id' => $this->locB->id,
        ])->json('data.id');
        $this->withHeaders($this->authHeaders('TR-AB-02-' . uniqid()))->postJson("/api/v1/stock/documents/{$doc1}/lines", ['goods_id' => $goods->id, 'quantity' => 30]);
        $this->withHeaders($this->authHeaders('TR-AB-03-' . uniqid()))->postJson("/api/v1/stock/documents/{$doc1}/submit");
        $this->withHeaders($this->authHeaders('TR-AB-04-' . uniqid()))->postJson("/api/v1/stock/documents/{$doc1}/approve");

        // Doc 2: B -> A 40
        $doc2 = $this->withHeaders($this->authHeaders('TR-BA-01-' . uniqid()))->postJson('/api/v1/stock/documents', [
            'document_type' => 'TRANSFER',
            'source_warehouse_id' => $this->warehouse->id,
            'source_location_id' => $this->locB->id,
            'destination_warehouse_id' => $this->warehouse->id,
            'destination_location_id' => $this->locA->id,
        ])->json('data.id');
        $this->withHeaders($this->authHeaders('TR-BA-02-' . uniqid()))->postJson("/api/v1/stock/documents/{$doc2}/lines", ['goods_id' => $goods->id, 'quantity' => 40]);
        $this->withHeaders($this->authHeaders('TR-BA-03-' . uniqid()))->postJson("/api/v1/stock/documents/{$doc2}/submit");
        $this->withHeaders($this->authHeaders('TR-BA-04-' . uniqid()))->postJson("/api/v1/stock/documents/{$doc2}/approve");

        // Execute concurrently
        $worker1 = $this->spawnWorker($doc1, 'TR-KEY-1-' . uniqid());
        $worker2 = $this->spawnWorker($doc2, 'TR-KEY-2-' . uniqid());

        $res1 = $this->collectWorkerOutput($worker1);
        $res2 = $this->collectWorkerOutput($worker2);

        $this->assertEquals('SUCCESS', $res1['data']['code'] ?? null);
        $this->assertEquals('SUCCESS', $res2['data']['code'] ?? null);

        $balA = StockBalance::where('organization_id', $this->orgId)->where('location_id', $this->locA->id)->where('goods_id', $goods->id)->value('on_hand');
        $balB = StockBalance::where('organization_id', $this->orgId)->where('location_id', $this->locB->id)->where('goods_id', $goods->id)->value('on_hand');

        $this->assertEquals('110.0000', $balA, 'LocA must have 100 - 30 + 40 = 110');
        $this->assertEquals('90.0000', $balB, 'LocB must have 100 + 30 - 40 = 90');
        $this->assertEquals(200.0, (float) $balA + (float) $balB, 'Total system stock must remain exactly 200');
    }
}
