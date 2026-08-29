<?php

namespace Tests\Feature;

use Modules\AuthenticationAudit\Models\Organization;
use Modules\AuthenticationAudit\Models\User;
use Modules\InventoryTransaction\Models\StockBalance;
use Modules\InventoryTransaction\Models\StockReservation;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Product;
use Modules\MasterData\Models\Unit;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;
use Tests\TestCase;

class StockReservationConcurrencyTest extends TestCase
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

    protected function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'X-Organization-Id' => $this->orgId,
        ];
    }

    protected function spawnWorker(string $documentId, int $lineId): array
    {
        $phpBinary = PHP_BINARY;
        $artisanPath = base_path('artisan');
        $cmd = "\"{$phpBinary}\" \"{$artisanPath}\" stock:reserve-worker \"{$documentId}\" \"{$lineId}\" \"{$this->userId}\" \"{$this->orgId}\"";

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

    public function test_concurrent_reservations_prevent_over_reservation(): void
    {
        $goods = Goods::create([
            'organization_id' => $this->orgId,
            'product_id' => $this->product->id,
            'unit_id' => $this->unit->id,
            'sku' => 'RES-RACE-' . uniqid(),
            'name' => 'Res Race Goods',
            'pack_size' => 1,
            'is_lot_tracked' => false,
            'is_serial_tracked' => false,
            'is_active' => true,
        ]);

        // 1. Initial Stock: on_hand = 10, reserved = 0
        StockBalance::create([
            'organization_id' => $this->orgId,
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->locA->id,
            'goods_id' => $goods->id,
            'on_hand' => '10.0000',
            'reserved' => '0.0000',
        ]);

        // 2. Prepare Doc A (Issue 8) -> Submit
        $docA = $this->withHeaders($this->authHeaders())->postJson('/api/v1/stock/documents', [
            'document_type' => 'ISSUE',
            'source_warehouse_id' => $this->warehouse->id,
            'source_location_id' => $this->locA->id,
        ])->json('data.id');
        $lineA = $this->withHeaders($this->authHeaders())->postJson("/api/v1/stock/documents/{$docA}/lines", ['goods_id' => $goods->id, 'quantity' => 8])->json('data.id');
        $this->withHeaders($this->authHeaders())->postJson("/api/v1/stock/documents/{$docA}/submit");

        // 3. Prepare Doc B (Issue 8) -> Submit
        $docB = $this->withHeaders($this->authHeaders())->postJson('/api/v1/stock/documents', [
            'document_type' => 'ISSUE',
            'source_warehouse_id' => $this->warehouse->id,
            'source_location_id' => $this->locA->id,
        ])->json('data.id');
        $lineB = $this->withHeaders($this->authHeaders())->postJson("/api/v1/stock/documents/{$docB}/lines", ['goods_id' => $goods->id, 'quantity' => 8])->json('data.id');
        $this->withHeaders($this->authHeaders())->postJson("/api/v1/stock/documents/{$docB}/submit");

        // 4. Launch concurrent reservation workers
        $workerA = $this->spawnWorker($docA, (int) $lineA);
        $workerB = $this->spawnWorker($docB, (int) $lineB);

        $resA = $this->collectWorkerOutput($workerA);
        $resB = $this->collectWorkerOutput($workerB);

        $codes = [$resA['data']['code'] ?? 'UNKNOWN', $resB['data']['code'] ?? 'UNKNOWN'];

        // Assert: Exactly ONE is SUCCESS, exactly ONE is INSUFFICIENT_AVAILABLE_STOCK
        $this->assertContains('SUCCESS', $codes);
        $this->assertContains('INSUFFICIENT_AVAILABLE_STOCK', $codes);

        // 5. Verify Database State: on_hand = 10, reserved = 8, available = 2 (NEVER reserved = 16!)
        $balance = StockBalance::where('organization_id', $this->orgId)
            ->where('warehouse_id', $this->warehouse->id)
            ->where('location_id', $this->locA->id)
            ->where('goods_id', $goods->id)
            ->first();

        $this->assertEquals('10.0000', $balance->on_hand);
        $this->assertEquals('8.0000', $balance->reserved);

        // Exactly 1 Active reservation record
        $this->assertEquals(1, StockReservation::where('goods_id', $goods->id)->where('status', 'ACTIVE')->count());
    }
}
