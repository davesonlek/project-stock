<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\InventoryTransaction\Enums\SerialNumberStatus;
use Modules\InventoryTransaction\Enums\StockReservationStatus;
use Modules\InventoryTransaction\Models\SerialNumber;
use Modules\InventoryTransaction\Models\StockBalance;
use Modules\InventoryTransaction\Models\StockLot;
use Modules\InventoryTransaction\Models\StockLotBalance;
use Modules\InventoryTransaction\Models\StockReservation;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Product;
use Modules\MasterData\Models\Unit;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;
use Tests\TestCase;

class ReservationReconciliationTest extends TestCase
{
    use DatabaseTransactions;

    protected string $token;
    protected string $orgId;
    protected Warehouse $warehouse;
    protected WarehouseLocation $locA;
    protected Goods $stdGoods;
    protected Goods $lotGoods;
    protected Goods $serialGoods;

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

        $product = Product::where('organization_id', $this->orgId)->first();
        $unit = Unit::where('organization_id', $this->orgId)->first();

        $this->stdGoods = Goods::create([
            'organization_id' => $this->orgId,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'sku' => 'REC-STD-' . uniqid(),
            'name' => 'Recon Std Goods',
            'pack_size' => 1,
            'is_lot_tracked' => false,
            'is_serial_tracked' => false,
            'is_active' => true,
        ]);

        $this->lotGoods = Goods::create([
            'organization_id' => $this->orgId,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'sku' => 'REC-LOT-' . uniqid(),
            'name' => 'Recon Lot Goods',
            'pack_size' => 1,
            'is_lot_tracked' => true,
            'is_serial_tracked' => false,
            'is_active' => true,
        ]);

        $this->serialGoods = Goods::create([
            'organization_id' => $this->orgId,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'sku' => 'REC-SN-' . uniqid(),
            'name' => 'Recon Serial Goods',
            'pack_size' => 1,
            'is_lot_tracked' => false,
            'is_serial_tracked' => true,
            'is_active' => true,
        ]);
    }

    protected function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'X-Organization-Id' => $this->orgId,
        ];
    }

    public function test_active_reservations_sum_strictly_reconciles_with_stock_balance_reserved(): void
    {
        StockBalance::create([
            'organization_id' => $this->orgId,
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->locA->id,
            'goods_id' => $this->stdGoods->id,
            'on_hand' => '100.0000',
            'reserved' => '0.0000',
        ]);

        // Doc 1: Reserve 20
        $doc1 = $this->withHeaders($this->authHeaders())->postJson('/api/v1/stock/documents', [
            'document_type' => 'ISSUE',
            'source_warehouse_id' => $this->warehouse->id,
            'source_location_id' => $this->locA->id,
        ])->json('data.id');
        $l1 = $this->withHeaders($this->authHeaders())->postJson("/api/v1/stock/documents/{$doc1}/lines", ['goods_id' => $this->stdGoods->id, 'quantity' => 20])->json('data.id');
        $this->withHeaders($this->authHeaders())->postJson("/api/v1/stock/documents/{$doc1}/submit");
        $this->withHeaders($this->authHeaders())->postJson("/api/v1/stock/documents/{$doc1}/lines/{$l1}/reserve");

        // Doc 2: Reserve 35
        $doc2 = $this->withHeaders($this->authHeaders())->postJson('/api/v1/stock/documents', [
            'document_type' => 'ISSUE',
            'source_warehouse_id' => $this->warehouse->id,
            'source_location_id' => $this->locA->id,
        ])->json('data.id');
        $l2 = $this->withHeaders($this->authHeaders())->postJson("/api/v1/stock/documents/{$doc2}/lines", ['goods_id' => $this->stdGoods->id, 'quantity' => 35])->json('data.id');
        $this->withHeaders($this->authHeaders())->postJson("/api/v1/stock/documents/{$doc2}/submit");
        $this->withHeaders($this->authHeaders())->postJson("/api/v1/stock/documents/{$doc2}/lines/{$l2}/reserve");

        // Verify SUM(ACTIVE reservations) == stock_balances.reserved (55)
        $sumActive = StockReservation::where('organization_id', $this->orgId)
            ->where('goods_id', $this->stdGoods->id)
            ->where('status', StockReservationStatus::ACTIVE)
            ->sum('quantity');

        $reserved = StockBalance::where('organization_id', $this->orgId)
            ->where('warehouse_id', $this->warehouse->id)
            ->where('location_id', $this->locA->id)
            ->where('goods_id', $this->stdGoods->id)
            ->value('reserved');

        $this->assertEquals('55.0000', number_format((float) $reserved, 4, '.', ''));
        $this->assertEquals(number_format((float) $reserved, 4, '.', ''), number_format((float) $sumActive, 4, '.', ''));
    }

    public function test_lot_active_reservations_sum_strictly_reconciles_with_lot_balance_reserved(): void
    {
        $futureExp = Carbon::now()->addYear()->toDateString();
        $lot = StockLot::create([
            'organization_id' => $this->orgId,
            'goods_id' => $this->lotGoods->id,
            'lot_no' => 'LOT-RECON-' . uniqid(),
            'expired_at' => $futureExp,
        ]);

        StockLotBalance::create([
            'organization_id' => $this->orgId,
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->locA->id,
            'lot_id' => $lot->id,
            'on_hand' => '100.0000',
            'reserved' => '0.0000',
        ]);
        StockBalance::create([
            'organization_id' => $this->orgId,
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->locA->id,
            'goods_id' => $this->lotGoods->id,
            'on_hand' => '100.0000',
            'reserved' => '0.0000',
        ]);

        // Reserve 40
        $doc = $this->withHeaders($this->authHeaders())->postJson('/api/v1/stock/documents', [
            'document_type' => 'ISSUE',
            'source_warehouse_id' => $this->warehouse->id,
            'source_location_id' => $this->locA->id,
        ])->json('data.id');
        $l = $this->withHeaders($this->authHeaders())->postJson("/api/v1/stock/documents/{$doc}/lines", ['goods_id' => $this->lotGoods->id, 'lot_id' => $lot->id, 'quantity' => 40])->json('data.id');
        $this->withHeaders($this->authHeaders())->postJson("/api/v1/stock/documents/{$doc}/submit");
        $this->withHeaders($this->authHeaders())->postJson("/api/v1/stock/documents/{$doc}/lines/{$l}/reserve");

        $lotReserved = StockLotBalance::where('lot_id', $lot->id)->where('location_id', $this->locA->id)->value('reserved');
        $sumLotRes = StockReservation::where('lot_id', $lot->id)->where('status', StockReservationStatus::ACTIVE)->sum('quantity');

        $this->assertEquals('40.0000', number_format((float) $lotReserved, 4, '.', ''));
        $this->assertEquals(number_format((float) $lotReserved, 4, '.', ''), number_format((float) $sumLotRes, 4, '.', ''));
    }

    public function test_serial_reserved_count_strictly_reconciles_with_reservation_quantity(): void
    {
        $sn1 = 'SN-RECON-1-' . uniqid();
        $sn2 = 'SN-RECON-2-' . uniqid();

        SerialNumber::create(['organization_id' => $this->orgId, 'goods_id' => $this->serialGoods->id, 'serial_no' => $sn1, 'warehouse_id' => $this->warehouse->id, 'location_id' => $this->locA->id, 'status' => SerialNumberStatus::IN_STOCK]);
        SerialNumber::create(['organization_id' => $this->orgId, 'goods_id' => $this->serialGoods->id, 'serial_no' => $sn2, 'warehouse_id' => $this->warehouse->id, 'location_id' => $this->locA->id, 'status' => SerialNumberStatus::IN_STOCK]);

        StockBalance::create([
            'organization_id' => $this->orgId,
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->locA->id,
            'goods_id' => $this->serialGoods->id,
            'on_hand' => '2.0000',
            'reserved' => '0.0000',
        ]);

        // Reserve 2 serials
        $doc = $this->withHeaders($this->authHeaders())->postJson('/api/v1/stock/documents', [
            'document_type' => 'ISSUE',
            'source_warehouse_id' => $this->warehouse->id,
            'source_location_id' => $this->locA->id,
        ])->json('data.id');
        $l = $this->withHeaders($this->authHeaders())->postJson("/api/v1/stock/documents/{$doc}/lines", ['goods_id' => $this->serialGoods->id, 'quantity' => 2, 'serials' => [$sn1, $sn2]])->json('data.id');
        $this->withHeaders($this->authHeaders())->postJson("/api/v1/stock/documents/{$doc}/submit");
        $this->withHeaders($this->authHeaders())->postJson("/api/v1/stock/documents/{$doc}/lines/{$l}/reserve");

        $reservedSerialsCount = SerialNumber::where('organization_id', $this->orgId)
            ->where('goods_id', $this->serialGoods->id)
            ->where('status', SerialNumberStatus::RESERVED)
            ->count();

        $this->assertEquals(2, $reservedSerialsCount);
    }
}
