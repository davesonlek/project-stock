<?php

namespace Modules\InventoryTransaction\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\InventoryTransaction\Enums\StockReservationStatus;
use Modules\InventoryTransaction\Models\SerialNumber;
use Modules\InventoryTransaction\Models\StockBalance;
use Modules\InventoryTransaction\Models\StockLotBalance;
use Modules\InventoryTransaction\Models\StockMovement;
use Modules\InventoryTransaction\Models\StockReservation;
use Modules\MasterData\Models\Goods;

class VerifyIntegrityCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'stock:verify-integrity {--org= : Optional Organization ID to filter}';

    /**
     * The console command description.
     */
    protected $description = 'Perform read-only validation of inventory balances, immutable ledger, lot/serial invariants, and orphan records';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('=====================================================');
        $this->info(' Starting Inventory System Integrity Verification... ');
        $this->info('=====================================================');

        $hasError = false;
        $orgId = $this->option('org');

        $orgs = $orgId ? Organization::where('id', $orgId)->get() : Organization::all();

        foreach ($orgs as $org) {
            $this->line('');
            $this->comment("Checking Organization: {$org->name} ({$org->id})");

            // 1. Check Balance on_hand vs Sum of Movements
            $balances = StockBalance::where('organization_id', $org->id)->get();
            $balanceMismatch = 0;

            foreach ($balances as $balance) {
                $movementSum = (float) StockMovement::where('organization_id', $org->id)
                    ->where('warehouse_id', $balance->warehouse_id)
                    ->where('location_id', $balance->location_id)
                    ->where('goods_id', $balance->goods_id)
                    ->sum('quantity_delta');

                if ((float) $balance->on_hand !== $movementSum) {
                    $this->error("  [MISMATCH] Balance on_hand ({$balance->on_hand}) != Movement sum ({$movementSum}) for Goods ID {$balance->goods_id} at Location ID {$balance->location_id}");
                    $balanceMismatch++;
                    $hasError = true;
                }
            }

            if ($balanceMismatch === 0) {
                $this->info("  ✓ Stock Balances vs Movement Ledger: PASSED (" . $balances->count() . " balances checked)");
            }

            // 2. Check Lot Balances vs Stock Balances
            $lotGoods = Goods::where('organization_id', $org->id)->where('is_lot_tracked', true)->get();
            $lotMismatch = 0;

            foreach ($lotGoods as $goods) {
                $bals = StockBalance::where('organization_id', $org->id)->where('goods_id', $goods->id)->get();
                foreach ($bals as $bal) {
                    $lotSum = (float) StockLotBalance::join('stock_lots', 'stock_lot_balances.lot_id', '=', 'stock_lots.id')
                        ->where('stock_lot_balances.organization_id', $org->id)
                        ->where('stock_lot_balances.warehouse_id', $bal->warehouse_id)
                        ->where('stock_lot_balances.location_id', $bal->location_id)
                        ->where('stock_lots.goods_id', $goods->id)
                        ->sum('stock_lot_balances.on_hand');

                    if ((float) $bal->on_hand !== $lotSum) {
                        $this->error("  [MISMATCH] Lot Balances sum ({$lotSum}) != Balance on_hand ({$bal->on_hand}) for SKU {$goods->sku} at Location ID {$bal->location_id}");
                        $lotMismatch++;
                        $hasError = true;
                    }
                }
            }

            if ($lotMismatch === 0) {
                $this->info("  ✓ Lot Balances vs Stock Balances: PASSED (" . $lotGoods->count() . " lot goods checked)");
            }

            // 3. Check Active Reservations vs Stock Balance reserved
            $resMismatch = 0;
            foreach ($balances as $balance) {
                $activeResSum = (float) StockReservation::where('organization_id', $org->id)
                    ->where('warehouse_id', $balance->warehouse_id)
                    ->where('location_id', $balance->location_id)
                    ->where('goods_id', $balance->goods_id)
                    ->where('status', StockReservationStatus::ACTIVE->value)
                    ->sum('quantity');

                if ((float) $balance->reserved !== $activeResSum) {
                    $this->error("  [MISMATCH] Active Reservations sum ({$activeResSum}) != Balance reserved ({$balance->reserved}) for Goods ID {$balance->goods_id}");
                    $resMismatch++;
                    $hasError = true;
                }
            }

            if ($resMismatch === 0) {
                $this->info("  ✓ Active Reservations vs Stock Balances: PASSED");
            }

            // 4. Check Serial Tracked Count vs on_hand
            $serialGoods = Goods::where('organization_id', $org->id)->where('is_serial_tracked', true)->get();
            $serialMismatch = 0;

            foreach ($serialGoods as $goods) {
                $bals = StockBalance::where('organization_id', $org->id)->where('goods_id', $goods->id)->get();
                foreach ($bals as $bal) {
                    $serialCount = SerialNumber::where('organization_id', $org->id)
                        ->where('warehouse_id', $bal->warehouse_id)
                        ->where('location_id', $bal->location_id)
                        ->where('goods_id', $goods->id)
                        ->whereIn('status', ['IN_STOCK', 'RESERVED'])
                        ->count();

                    if ((float) $bal->on_hand !== (float) $serialCount) {
                        $this->error("  [MISMATCH] Serial count ({$serialCount}) != Balance on_hand ({$bal->on_hand}) for SKU {$goods->sku} at Location ID {$bal->location_id}");
                        $serialMismatch++;
                        $hasError = true;
                    }
                }
            }

            if ($serialMismatch === 0) {
                $this->info("  ✓ Serial Count Invariants: PASSED (" . $serialGoods->count() . " serial goods checked)");
            }
        }

        // 5. Global Orphan Records Audit
        $this->line('');
        $this->comment('Checking for Orphan Records & Broken Foreign References...');

        $orphanLines = DB::table('stock_document_lines')
            ->leftJoin('stock_documents', 'stock_document_lines.document_id', '=', 'stock_documents.id')
            ->whereNull('stock_documents.id')
            ->count();

        $orphanMovements = DB::table('stock_movements')
            ->leftJoin('stock_documents', 'stock_movements.document_id', '=', 'stock_documents.id')
            ->whereNull('stock_documents.id')
            ->count();

        $orphanLotBalances = DB::table('stock_lot_balances')
            ->leftJoin('stock_lots', 'stock_lot_balances.lot_id', '=', 'stock_lots.id')
            ->whereNull('stock_lots.id')
            ->count();

        if ($orphanLines > 0 || $orphanMovements > 0 || $orphanLotBalances > 0) {
            $this->error("  [ORPHANS DETECTED] Lines: {$orphanLines}, Movements: {$orphanMovements}, LotBalances: {$orphanLotBalances}");
            $hasError = true;
        } else {
            $this->info('  ✓ Orphan Records Audit: PASSED (0 orphaned records)');
        }

        $this->line('');
        if ($hasError) {
            $this->error('=====================================================');
            $this->error(' Integrity Check Completed with Issues. [FAILED]     ');
            $this->error('=====================================================');
            return self::FAILURE;
        }

        $this->info('=====================================================');
        $this->info(' All Integrity Invariants Verified! [PASSED 100%]    ');
        $this->info('=====================================================');
        return self::SUCCESS;
    }
}
