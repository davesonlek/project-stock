<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\AuthenticationAudit\Models\User;
use Modules\InventoryTransaction\Models\SerialNumber;
use Modules\InventoryTransaction\Models\StockBalance;
use Modules\InventoryTransaction\Models\StockLot;
use Modules\InventoryTransaction\Models\StockLotBalance;
use Modules\InventoryTransaction\Models\StockMovement;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Product;
use Modules\MasterData\Models\Unit;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;
use Tests\TestCase;

class StockPostingConcurrencyTest extends TestCase
{
    protected string $token;
    protected string $orgId;
    protected string $userId;
    protected Warehouse $warehouse;
    protected WarehouseLocation $locA;
    protected Product $product;
    protected Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $org = Organization::where('name', 'Global Supply Co.')->first();
        $this->orgId = $org->id;

        $user = User::where('email', 'manager@test.com')->first();
        $this->userId = $user->id;

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'manager@test.com',
            'password' => 'password123',
        ]);
        $this->token = $login->json('data.access_token');

        $this->warehouse = Warehouse::where('organization_id', $this->orgId)->where('code', 'MAIN')->first();
        $this->locA = WarehouseLocation::where('warehouse_id', $this->warehouse->id)->where('code', 'A-01-01')->first();

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

    protected function createIsolatedGoods(bool $lotTracked = false, bool $serialTracked = false): Goods
    {
        $unique = uniqid();
        return Goods::create([
            'organization_id' => $this->orgId,
            'product_id' => $this->product->id,
            'unit_id' => $this->unit->id,
            'sku' => "CONC-SKU-{$unique}",
            'name' => "Conc Goods {$unique}",
            'pack_size' => 1,
            'is_lot_tracked' => $lotTracked,
            'is_serial_tracked' => $serialTracked,
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

    public function test_primary_concurrency_issue_race_condition(): void
    {
        $goods = $this->createIsolatedGoods(false, false);

        // 1. Initial Stock = 10
        StockBalance::create([
            'organization_id' => $this->orgId,
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->locA->id,
            'goods_id' => $goods->id,
            'on_hand' => '10.0000',
            'reserved' => '0.0000',
        ]);

        // 2. Prepare Document A (ISSUE 8) -> APPROVED
        $docA = $this->withHeaders($this->authHeaders('RACE-DOC-A01-' . uniqid()))->postJson('/api/v1/stock/documents', [
            'document_type' => 'ISSUE',
            'source_warehouse_id' => $this->warehouse->id,
            'source_location_id' => $this->locA->id,
        ])->json('data.id');
        $this->withHeaders($this->authHeaders('RACE-DOC-A02-' . uniqid()))->postJson("/api/v1/stock/documents/{$docA}/lines", ['goods_id' => $goods->id, 'quantity' => 8]);
        $this->withHeaders($this->authHeaders('RACE-DOC-A03-' . uniqid()))->postJson("/api/v1/stock/documents/{$docA}/submit");
        $this->withHeaders($this->authHeaders('RACE-DOC-A04-' . uniqid()))->postJson("/api/v1/stock/documents/{$docA}/approve");

        // 3. Prepare Document B (ISSUE 8) -> APPROVED
        $docB = $this->withHeaders($this->authHeaders('RACE-DOC-B01-' . uniqid()))->postJson('/api/v1/stock/documents', [
            'document_type' => 'ISSUE',
            'source_warehouse_id' => $this->warehouse->id,
            'source_location_id' => $this->locA->id,
        ])->json('data.id');
        $this->withHeaders($this->authHeaders('RACE-DOC-B02-' . uniqid()))->postJson("/api/v1/stock/documents/{$docB}/lines", ['goods_id' => $goods->id, 'quantity' => 8]);
        $this->withHeaders($this->authHeaders('RACE-DOC-B03-' . uniqid()))->postJson("/api/v1/stock/documents/{$docB}/submit");
        $this->withHeaders($this->authHeaders('RACE-DOC-B04-' . uniqid()))->postJson("/api/v1/stock/documents/{$docB}/approve");

        // 4. Spawn Both Processes Concurrently
        $workerA = $this->spawnWorker($docA, 'RACE-KEY-A-' . uniqid());
        $workerB = $this->spawnWorker($docB, 'RACE-KEY-B-' . uniqid());

        // 5. Collect Results
        $resA = $this->collectWorkerOutput($workerA);
        $resB = $this->collectWorkerOutput($workerB);

        $codes = [$resA['data']['code'] ?? 'UNKNOWN', $resB['data']['code'] ?? 'UNKNOWN'];

        // Assert: Exactly ONE is SUCCESS, exactly ONE is INSUFFICIENT_AVAILABLE_STOCK
        $this->assertContains('SUCCESS', $codes);
        $this->assertContains('INSUFFICIENT_AVAILABLE_STOCK', $codes);

        // 6. Verify Final Database State: Stock must be 2 (NEVER -6!)
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

    public function test_concurrent_balance_row_creation(): void
    {
        $goods = $this->createIsolatedGoods(false, false);

        // Prepare Doc A: Receive 10
        $docA = $this->withHeaders($this->authHeaders('C-REC-A01-' . uniqid()))->postJson('/api/v1/stock/documents', [
            'document_type' => 'RECEIVE',
            'destination_warehouse_id' => $this->warehouse->id,
            'destination_location_id' => $this->locA->id,
        ])->json('data.id');
        $this->withHeaders($this->authHeaders('C-REC-A02-' . uniqid()))->postJson("/api/v1/stock/documents/{$docA}/lines", ['goods_id' => $goods->id, 'quantity' => 10]);
        $this->withHeaders($this->authHeaders('C-REC-A03-' . uniqid()))->postJson("/api/v1/stock/documents/{$docA}/submit");
        $this->withHeaders($this->authHeaders('C-REC-A04-' . uniqid()))->postJson("/api/v1/stock/documents/{$docA}/approve");

        // Prepare Doc B: Receive 20
        $docB = $this->withHeaders($this->authHeaders('C-REC-B01-' . uniqid()))->postJson('/api/v1/stock/documents', [
            'document_type' => 'RECEIVE',
            'destination_warehouse_id' => $this->warehouse->id,
            'destination_location_id' => $this->locA->id,
        ])->json('data.id');
        $this->withHeaders($this->authHeaders('C-REC-B02-' . uniqid()))->postJson("/api/v1/stock/documents/{$docB}/lines", ['goods_id' => $goods->id, 'quantity' => 20]);
        $this->withHeaders($this->authHeaders('C-REC-B03-' . uniqid()))->postJson("/api/v1/stock/documents/{$docB}/submit");
        $this->withHeaders($this->authHeaders('C-REC-B04-' . uniqid()))->postJson("/api/v1/stock/documents/{$docB}/approve");

        // Run both workers concurrently
        $workerA = $this->spawnWorker($docA, 'C-REC-KEY-A-' . uniqid());
        $workerB = $this->spawnWorker($docB, 'C-REC-KEY-B-' . uniqid());

        $resA = $this->collectWorkerOutput($workerA);
        $resB = $this->collectWorkerOutput($workerB);

        $this->assertEquals('SUCCESS', $resA['data']['code'] ?? null);
        $this->assertEquals('SUCCESS', $resB['data']['code'] ?? null);

        // Exactly 1 StockBalance row with on_hand = 30
        $balanceRows = StockBalance::where('organization_id', $this->orgId)
            ->where('warehouse_id', $this->warehouse->id)
            ->where('location_id', $this->locA->id)
            ->where('goods_id', $goods->id)
            ->get();

        $this->assertCount(1, $balanceRows);
        $this->assertEquals('30.0000', $balanceRows->first()->on_hand);
    }

    public function test_concurrent_lot_issue(): void
    {
        $goods = $this->createIsolatedGoods(true, false);
        $futureExp = Carbon::now()->addYear()->toDateString();
        $lot = StockLot::create([
            'organization_id' => $this->orgId,
            'goods_id' => $goods->id,
            'lot_no' => 'LOT-CONC-' . uniqid(),
            'expired_at' => $futureExp,
        ]);

        // Initial lot on_hand = 10, main on_hand = 10
        StockLotBalance::create([
            'organization_id' => $this->orgId,
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->locA->id,
            'lot_id' => $lot->id,
            'on_hand' => '10.0000',
            'reserved' => '0.0000',
        ]);
        StockBalance::create([
            'organization_id' => $this->orgId,
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->locA->id,
            'goods_id' => $goods->id,
            'on_hand' => '10.0000',
            'reserved' => '0.0000',
        ]);

        // Doc A: Issue 8 of LOT
        $docA = $this->withHeaders($this->authHeaders('L-ISS-A01-' . uniqid()))->postJson('/api/v1/stock/documents', [
            'document_type' => 'ISSUE',
            'source_warehouse_id' => $this->warehouse->id,
            'source_location_id' => $this->locA->id,
        ])->json('data.id');
        $this->withHeaders($this->authHeaders('L-ISS-A02-' . uniqid()))->postJson("/api/v1/stock/documents/{$docA}/lines", ['goods_id' => $goods->id, 'lot_id' => $lot->id, 'quantity' => 8]);
        $this->withHeaders($this->authHeaders('L-ISS-A03-' . uniqid()))->postJson("/api/v1/stock/documents/{$docA}/submit");
        $this->withHeaders($this->authHeaders('L-ISS-A04-' . uniqid()))->postJson("/api/v1/stock/documents/{$docA}/approve");

        // Doc B: Issue 8 of LOT
        $docB = $this->withHeaders($this->authHeaders('L-ISS-B01-' . uniqid()))->postJson('/api/v1/stock/documents', [
            'document_type' => 'ISSUE',
            'source_warehouse_id' => $this->warehouse->id,
            'source_location_id' => $this->locA->id,
        ])->json('data.id');
        $this->withHeaders($this->authHeaders('L-ISS-B02-' . uniqid()))->postJson("/api/v1/stock/documents/{$docB}/lines", ['goods_id' => $goods->id, 'lot_id' => $lot->id, 'quantity' => 8]);
        $this->withHeaders($this->authHeaders('L-ISS-B03-' . uniqid()))->postJson("/api/v1/stock/documents/{$docB}/submit");
        $this->withHeaders($this->authHeaders('L-ISS-B04-' . uniqid()))->postJson("/api/v1/stock/documents/{$docB}/approve");

        $workerA = $this->spawnWorker($docA, 'L-ISS-KEY-A-' . uniqid());
        $workerB = $this->spawnWorker($docB, 'L-ISS-KEY-B-' . uniqid());

        $resA = $this->collectWorkerOutput($workerA);
        $resB = $this->collectWorkerOutput($workerB);

        $codes = [$resA['data']['code'] ?? 'UNKNOWN', $resB['data']['code'] ?? 'UNKNOWN'];
        $this->assertContains('SUCCESS', $codes);
        $this->assertContains('INSUFFICIENT_AVAILABLE_STOCK', $codes);

        // Final lot balance = 2, final main balance = 2
        $finalLotBal = StockLotBalance::where('lot_id', $lot->id)->where('location_id', $this->locA->id)->value('on_hand');
        $finalMainBal = StockBalance::where('goods_id', $goods->id)->where('location_id', $this->locA->id)->value('on_hand');

        $this->assertEquals('2.0000', $finalLotBal);
        $this->assertEquals('2.0000', $finalMainBal);
    }

    public function test_concurrent_serial_uniqueness(): void
    {
        $goods = $this->createIsolatedGoods(false, true);
        $serialNo = 'SN-RACE-' . uniqid();

        // Doc A: Receive unique serial
        $docA = $this->withHeaders($this->authHeaders('S-REC-A01-' . uniqid()))->postJson('/api/v1/stock/documents', [
            'document_type' => 'RECEIVE',
            'destination_warehouse_id' => $this->warehouse->id,
            'destination_location_id' => $this->locA->id,
        ])->json('data.id');
        $this->withHeaders($this->authHeaders('S-REC-A02-' . uniqid()))->postJson("/api/v1/stock/documents/{$docA}/lines", ['goods_id' => $goods->id, 'quantity' => 1, 'serials' => [$serialNo]]);
        $this->withHeaders($this->authHeaders('S-REC-A03-' . uniqid()))->postJson("/api/v1/stock/documents/{$docA}/submit");
        $this->withHeaders($this->authHeaders('S-REC-A04-' . uniqid()))->postJson("/api/v1/stock/documents/{$docA}/approve");

        // Doc B: Receive same unique serial
        $docB = $this->withHeaders($this->authHeaders('S-REC-B01-' . uniqid()))->postJson('/api/v1/stock/documents', [
            'document_type' => 'RECEIVE',
            'destination_warehouse_id' => $this->warehouse->id,
            'destination_location_id' => $this->locA->id,
        ])->json('data.id');
        $this->withHeaders($this->authHeaders('S-REC-B02-' . uniqid()))->postJson("/api/v1/stock/documents/{$docB}/lines", ['goods_id' => $goods->id, 'quantity' => 1, 'serials' => [$serialNo]]);
        $this->withHeaders($this->authHeaders('S-REC-B03-' . uniqid()))->postJson("/api/v1/stock/documents/{$docB}/submit");
        $this->withHeaders($this->authHeaders('S-REC-B04-' . uniqid()))->postJson("/api/v1/stock/documents/{$docB}/approve");

        $workerA = $this->spawnWorker($docA, 'S-REC-KEY-A-' . uniqid());
        $workerB = $this->spawnWorker($docB, 'S-REC-KEY-B-' . uniqid());

        $resA = $this->collectWorkerOutput($workerA);
        $resB = $this->collectWorkerOutput($workerB);

        $codes = [$resA['data']['code'] ?? 'UNKNOWN', $resB['data']['code'] ?? 'UNKNOWN'];
        $this->assertContains('SUCCESS', $codes);
        $this->assertContains('SERIAL_NUMBER_ALREADY_EXISTS', $codes);

        // Exactly 1 serial record in DB
        $this->assertEquals(1, SerialNumber::where('organization_id', $this->orgId)->where('serial_no', $serialNo)->count());
    }
}
