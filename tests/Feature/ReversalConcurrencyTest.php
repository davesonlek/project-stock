<?php

namespace Tests\Feature;

use Modules\AuthenticationAudit\Models\Organization;
use Modules\AuthenticationAudit\Models\User;
use Modules\InventoryTransaction\Models\StockBalance;
use Modules\InventoryTransaction\Models\StockDocument;
use Modules\InventoryTransaction\Models\StockMovement;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Product;
use Modules\MasterData\Models\Unit;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;
use Tests\TestCase;

class ReversalConcurrencyTest extends TestCase
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

        $user = User::where('email', 'admin@test.com')->first();
        $this->userId = $user->id;

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@test.com',
            'password' => 'password123',
        ]);
        $this->token = $login->json('data.access_token');

        $this->warehouse = Warehouse::where('organization_id', $this->orgId)->where('code', 'MAIN')->first();
        $this->locA = WarehouseLocation::where('warehouse_id', $this->warehouse->id)->where('code', 'A-01-01')->first();

        $this->product = Product::where('organization_id', $this->orgId)->first();
        $this->unit = Unit::where('organization_id', $this->orgId)->first();
    }

    protected function authHeaders(string $key = 'REV-RACE-001'): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'X-Organization-Id' => $this->orgId,
            'Idempotency-Key' => $key,
        ];
    }

    protected function spawnWorker(string $documentId, string $idempotencyKey): array
    {
        $phpBinary = PHP_BINARY;
        $artisanPath = base_path('artisan');
        $cmd = "\"{$phpBinary}\" \"{$artisanPath}\" stock:reverse-worker \"{$documentId}\" \"{$idempotencyKey}\" \"{$this->userId}\" \"{$this->orgId}\"";

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

    public function test_concurrent_reversals_prevent_double_reversal(): void
    {
        $goods = Goods::create([
            'organization_id' => $this->orgId,
            'product_id' => $this->product->id,
            'unit_id' => $this->unit->id,
            'sku' => 'REV-RACE-' . uniqid(),
            'name' => 'Rev Race Goods',
            'pack_size' => 1,
            'is_lot_tracked' => false,
            'is_serial_tracked' => false,
            'is_active' => true,
        ]);

        // 1. Post Receive of 100
        $doc = $this->withHeaders($this->authHeaders('RCV-RACE-01-' . uniqid()))->postJson('/api/v1/stock/documents', [
            'document_type' => 'RECEIVE',
            'destination_warehouse_id' => $this->warehouse->id,
            'destination_location_id' => $this->locA->id,
        ])->json('data.id');

        $this->withHeaders($this->authHeaders('RCV-RACE-02-' . uniqid()))->postJson("/api/v1/stock/documents/{$doc}/lines", ['goods_id' => $goods->id, 'quantity' => 100]);
        $this->withHeaders($this->authHeaders('RCV-RACE-03-' . uniqid()))->postJson("/api/v1/stock/documents/{$doc}/submit");
        $this->withHeaders($this->authHeaders('RCV-RACE-04-' . uniqid()))->postJson("/api/v1/stock/documents/{$doc}/approve");
        $postRes = $this->withHeaders($this->authHeaders('RCV-RACE-POST-' . uniqid()))->postJson("/api/v1/stock/documents/{$doc}/post");
        $postRes->assertStatus(200);

        // 2. Launch concurrent reversal workers with two DIFFERENT unique keys
        $key1 = 'REV-KEY-CONCURRENT-1-' . uniqid();
        $key2 = 'REV-KEY-CONCURRENT-2-' . uniqid();
        $worker1 = $this->spawnWorker($doc, $key1);
        $worker2 = $this->spawnWorker($doc, $key2);

        $res1 = $this->collectWorkerOutput($worker1);
        $res2 = $this->collectWorkerOutput($worker2);

        $codes = [$res1['data']['code'] ?? 'UNKNOWN', $res2['data']['code'] ?? 'UNKNOWN'];

        // Assert: Exactly ONE is SUCCESS, exactly ONE is DOCUMENT_ALREADY_REVERSED or IDEMPOTENCY_KEY_CONFLICT
        $this->assertTrue(in_array('SUCCESS', $codes, true), 'Expected one SUCCESS. Got: ' . json_encode([$res1, $res2]));

        $conflictCodes = ['DOCUMENT_ALREADY_REVERSED', 'IDEMPOTENCY_KEY_CONFLICT'];
        $hasConflict = false;
        foreach ($codes as $c) {
            if (in_array($c, $conflictCodes, true)) {
                $hasConflict = true;
                break;
            }
        }
        $this->assertTrue($hasConflict, 'Expected one worker to fail with a reversal conflict code');

        // 3. Verify Database State: on_hand = 0, exactly 1 reversal document, exactly 1 compensating movement
        $balance = StockBalance::where('organization_id', $this->orgId)
            ->where('warehouse_id', $this->warehouse->id)
            ->where('location_id', $this->locA->id)
            ->where('goods_id', $goods->id)
            ->first();

        $this->assertEquals('0.0000', $balance->on_hand);
        $this->assertEquals(1, StockDocument::where('reversal_of', $doc)->count());
        $this->assertEquals(1, StockMovement::where('movement_type', 'REVERSAL')->where('goods_id', $goods->id)->count());
    }
}
