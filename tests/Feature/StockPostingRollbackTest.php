<?php

namespace Tests\Feature;

use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\InventoryTransaction\Enums\StockDocumentStatus;
use Modules\InventoryTransaction\Models\StockBalance;
use Modules\InventoryTransaction\Models\StockDocument;
use Modules\InventoryTransaction\Models\StockMovement;
use Modules\InventoryTransaction\Services\Posting\ReceiveStockService;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;
use Tests\TestCase;

class StockPostingRollbackTest extends TestCase
{
    use DatabaseTransactions;

    protected string $token;
    protected string $orgId;
    protected Warehouse $warehouse;
    protected WarehouseLocation $locA;
    protected WarehouseLocation $locB;
    protected Goods $goods;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $org = Organization::where('name', 'Global Supply Co.')->first();
        $this->orgId = $org->id;

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'manager@test.com',
            'password' => 'password123',
        ]);
        $this->token = $login->json('data.access_token');

        $this->warehouse = Warehouse::where('organization_id', $this->orgId)->where('code', 'MAIN')->first();
        $this->locA = WarehouseLocation::where('warehouse_id', $this->warehouse->id)->where('code', 'A-01-01')->first();
        $this->locB = WarehouseLocation::where('warehouse_id', $this->warehouse->id)->where('code', 'A-01-02')->first();
        $this->goods = Goods::where('organization_id', $this->orgId)->where('is_lot_tracked', false)->where('is_serial_tracked', false)->first();
    }

    protected function authHeaders(string $idempotencyKey = 'ROLLBACK-001'): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'X-Organization-Id' => $this->orgId,
            'Idempotency-Key' => $idempotencyKey,
        ];
    }

    public function test_receive_transaction_rollback_reverts_all_balance_and_movement_changes(): void
    {
        // Initial balance 100
        $balance = StockBalance::updateOrCreate(
            ['organization_id' => $this->orgId, 'warehouse_id' => $this->warehouse->id, 'location_id' => $this->locA->id, 'goods_id' => $this->goods->id],
            ['on_hand' => '100.0000', 'reserved' => '0.0000']
        );

        $docRes = $this->withHeaders($this->authHeaders('RB-REC-01'))
            ->postJson('/api/v1/stock/documents', [
                'document_type' => 'RECEIVE',
                'destination_warehouse_id' => $this->warehouse->id,
                'destination_location_id' => $this->locA->id,
            ]);
        $docId = $docRes->json('data.id');

        $this->withHeaders($this->authHeaders('RB-REC-02'))->postJson("/api/v1/stock/documents/{$docId}/lines", [
            'goods_id' => $this->goods->id,
            'quantity' => 50,
        ]);
        $this->withHeaders($this->authHeaders('RB-REC-03'))->postJson("/api/v1/stock/documents/{$docId}/submit");
        $this->withHeaders($this->authHeaders('RB-REC-04'))->postJson("/api/v1/stock/documents/{$docId}/approve");

        // Bind mock ReceiveStockService that mutates balance then throws Exception
        $this->app->bind(ReceiveStockService::class, function ($app) {
            $realService = new ReceiveStockService(
                $app->make(\Modules\InventoryTransaction\Services\StockBalanceManager::class),
                $app->make(\Modules\InventoryTransaction\Services\StockLotManager::class),
                $app->make(\Modules\InventoryTransaction\Services\SerialNumberManager::class),
                $app->make(\Modules\InventoryTransaction\Services\StockMovementWriter::class)
            );
            return new class($realService) extends ReceiveStockService {
                public function __construct(protected ReceiveStockService $inner) {}
                public function post($orgId, $userId, $document, $lockedBalances, $lockedLotBalances): void
                {
                    $this->inner->post($orgId, $userId, $document, $lockedBalances, $lockedLotBalances);
                    throw new Exception('Simulated mid-transaction failure');
                }
            };
        });

        // Attempt POST
        try {
            $this->withHeaders($this->authHeaders('RB-REC-POST'))
                ->postJson("/api/v1/stock/documents/{$docId}/post");
        } catch (\Throwable $e) {
            // Expected
        }

        // Verify balance rolled back to 100
        $balance->refresh();
        $this->assertEquals('100.0000', $balance->on_hand);

        // Verify Document is still APPROVED
        $doc = StockDocument::find($docId);
        $this->assertEquals(StockDocumentStatus::APPROVED, $doc->status);

        // Verify NO movements inserted
        $this->assertEquals(0, StockMovement::where('document_id', $docId)->count());
    }
}
