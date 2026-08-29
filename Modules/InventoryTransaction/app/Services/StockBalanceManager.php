<?php

namespace Modules\InventoryTransaction\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\InventoryTransaction\Exceptions\StockDocumentException;
use Modules\InventoryTransaction\Models\StockBalance;

class StockBalanceManager
{
    /**
     * Ensure stock_balances rows exist using atomic INSERT ... ON CONFLICT DO NOTHING.
     */
    public function ensureRows(string $orgId, array $balanceKeys): void
    {
        if (empty($balanceKeys)) {
            return;
        }

        $now = now()->toDateTimeString();
        $values = [];
        $bindings = [];

        foreach ($balanceKeys as $k) {
            $values[] = '(?, ?, ?, ?, 0.0000, 0.0000, ?, ?)';
            $bindings[] = $orgId;
            $bindings[] = $k['warehouse_id'];
            $bindings[] = $k['location_id'];
            $bindings[] = $k['goods_id'];
            $bindings[] = $now;
            $bindings[] = $now;
        }

        $sql = 'INSERT INTO stock_balances (organization_id, warehouse_id, location_id, goods_id, on_hand, reserved, created_at, updated_at) VALUES '
            . implode(', ', $values)
            . ' ON CONFLICT (organization_id, warehouse_id, location_id, goods_id) DO NOTHING';

        DB::statement($sql, $bindings);
    }

    /**
     * Lock stock_balances rows ordered strictly by id ASC to avoid deadlocks.
     * Returns a Collection indexed by "warehouse_id:location_id:goods_id".
     */
    public function lockBalances(string $orgId, array $balanceKeys): Collection
    {
        if (empty($balanceKeys)) {
            return collect();
        }

        $query = StockBalance::where('organization_id', $orgId);

        $query->where(function ($q) use ($balanceKeys) {
            foreach ($balanceKeys as $k) {
                $q->orWhere(function ($sub) use ($k) {
                    $sub->where('warehouse_id', $k['warehouse_id'])
                        ->where('location_id', $k['location_id'])
                        ->where('goods_id', $k['goods_id']);
                });
            }
        });

        // Global deterministic lock order: ORDER BY id ASC
        $balances = $query->orderBy('id', 'asc')->lockForUpdate()->get();

        return $balances->keyBy(function (StockBalance $b) {
            return "{$b->warehouse_id}:{$b->location_id}:{$b->goods_id}";
        });
    }

    /**
     * Calculate available stock = on_hand - reserved.
     */
    public function getAvailable(StockBalance $balance): string
    {
        return bcsub((string) $balance->on_hand, (string) $balance->reserved, 4);
    }

    /**
     * Increment on_hand quantity using BCMath precision.
     */
    public function incrementOnHand(StockBalance $balance, string $quantity): void
    {
        $newOnHand = bcadd((string) $balance->on_hand, $quantity, 4);
        $balance->update(['on_hand' => $newOnHand]);
    }

    /**
     * Decrement on_hand quantity with sufficiency and available stock checks.
     */
    public function decrementOnHand(StockBalance $balance, string $quantity): void
    {
        $available = $this->getAvailable($balance);

        if (bccomp($available, $quantity, 4) < 0) {
            throw new StockDocumentException(
                "Insufficient available stock for goods ID {$balance->goods_id}. Available: {$available}, Required: {$quantity}",
                'INSUFFICIENT_AVAILABLE_STOCK',
                409
            );
        }

        $newOnHand = bcsub((string) $balance->on_hand, $quantity, 4);
        $balance->update(['on_hand' => $newOnHand]);
    }

    /**
     * Set exact on_hand quantity (for Adjustments) with reserved stock boundary check.
     */
    public function setOnHand(StockBalance $balance, string $countedQuantity): void
    {
        if (bccomp($countedQuantity, (string) $balance->reserved, 4) < 0) {
            throw new StockDocumentException(
                "Counted quantity ({$countedQuantity}) cannot be less than reserved stock ({$balance->reserved})",
                'ADJUSTMENT_BELOW_RESERVED_STOCK',
                409
            );
        }

        $balance->update(['on_hand' => $countedQuantity]);
    }

    /**
     * Increment reserved stock with available stock check.
     */
    public function incrementReserved(StockBalance $balance, string $quantity): void
    {
        $available = $this->getAvailable($balance);

        if (bccomp($available, $quantity, 4) < 0) {
            throw new StockDocumentException(
                "Insufficient available stock for reservation of goods ID {$balance->goods_id}. Available: {$available}, Required: {$quantity}",
                'INSUFFICIENT_AVAILABLE_STOCK',
                409
            );
        }

        $newReserved = bcadd((string) $balance->reserved, $quantity, 4);
        $balance->update(['reserved' => $newReserved]);
    }

    /**
     * Decrement reserved stock.
     */
    public function decrementReserved(StockBalance $balance, string $quantity): void
    {
        if (bccomp((string) $balance->reserved, $quantity, 4) < 0) {
            throw new StockDocumentException(
                "Reserved stock is less than released quantity for goods ID {$balance->goods_id}. Reserved: {$balance->reserved}, Releasing: {$quantity}",
                'RESERVATION_BALANCE_MISMATCH',
                409
            );
        }

        $newReserved = bcsub((string) $balance->reserved, $quantity, 4);
        $balance->update(['reserved' => $newReserved]);
    }

    /**
     * Consume reserved stock on Issue posting.
     */
    public function consumeReserved(StockBalance $balance, string $quantity): void
    {
        if (bccomp((string) $balance->on_hand, $quantity, 4) < 0 || bccomp((string) $balance->reserved, $quantity, 4) < 0) {
            throw new StockDocumentException(
                "Cannot consume reservation: balance mismatch for goods ID {$balance->goods_id}",
                'RESERVATION_BALANCE_MISMATCH',
                409
            );
        }

        $newOnHand = bcsub((string) $balance->on_hand, $quantity, 4);
        $newReserved = bcsub((string) $balance->reserved, $quantity, 4);
        $balance->update(['on_hand' => $newOnHand, 'reserved' => $newReserved]);
    }
}
