<?php

namespace Modules\InventoryTransaction\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\InventoryTransaction\Exceptions\StockDocumentException;
use Modules\InventoryTransaction\Models\StockLot;
use Modules\InventoryTransaction\Models\StockLotBalance;

class StockLotManager
{
    /**
     * Resolve existing lot or create new stock_lot record safely.
     */
    public function resolveOrCreateLot(
        string $orgId,
        int $goodsId,
        string $lotNo,
        ?string $manufacturedAt = null,
        ?string $expiredAt = null,
        bool $isReceive = false
    ): StockLot {
        // Find existing lot with lock
        $lot = StockLot::where('organization_id', $orgId)
            ->where('goods_id', $goodsId)
            ->where('lot_no', $lotNo)
            ->lockForUpdate()
            ->first();

        $today = Carbon::today()->toDateString();

        if ($lot) {
            // Check metadata conflict
            $existingMfg = $lot->manufactured_at ? Carbon::parse($lot->manufactured_at)->toDateString() : null;
            $existingExp = $lot->expired_at ? Carbon::parse($lot->expired_at)->toDateString() : null;
            $inputMfg = $manufacturedAt ? Carbon::parse($manufacturedAt)->toDateString() : null;
            $inputExp = $expiredAt ? Carbon::parse($expiredAt)->toDateString() : null;

            if ($inputMfg !== null && $existingMfg !== null && $inputMfg !== $existingMfg) {
                throw new StockDocumentException(
                    "Lot '{$lotNo}' manufactured date conflict. Existing: {$existingMfg}, Provided: {$inputMfg}",
                    'LOT_METADATA_CONFLICT',
                    409
                );
            }

            if ($inputExp !== null && $existingExp !== null && $inputExp !== $existingExp) {
                throw new StockDocumentException(
                    "Lot '{$lotNo}' expired date conflict. Existing: {$existingExp}, Provided: {$inputExp}",
                    'LOT_METADATA_CONFLICT',
                    409
                );
            }

            // Check if expired on receive
            if ($isReceive && $existingExp !== null && $existingExp < $today) {
                throw new StockDocumentException(
                    "Cannot receive expired lot '{$lotNo}'. Expired at: {$existingExp}",
                    'LOT_ALREADY_EXPIRED',
                    409
                );
            }

            return $lot;
        }

        // New lot creation
        if ($isReceive && $expiredAt !== null && Carbon::parse($expiredAt)->toDateString() < $today) {
            throw new StockDocumentException(
                "Cannot receive expired lot '{$lotNo}'. Expired at: {$expiredAt}",
                'LOT_ALREADY_EXPIRED',
                409
            );
        }

        try {
            return StockLot::create([
                'organization_id' => $orgId,
                'goods_id' => $goodsId,
                'lot_no' => $lotNo,
                'manufactured_at' => $manufacturedAt,
                'expired_at' => $expiredAt,
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // In case of concurrent creation, query again with lock
            $lot = StockLot::where('organization_id', $orgId)
                ->where('goods_id', $goodsId)
                ->where('lot_no', $lotNo)
                ->lockForUpdate()
                ->firstOrFail();

            return $lot;
        }
    }

    /**
     * Ensure stock_lot_balances rows exist using atomic INSERT ... ON CONFLICT DO NOTHING.
     */
    public function ensureRows(string $orgId, array $lotBalanceKeys): void
    {
        if (empty($lotBalanceKeys)) {
            return;
        }

        $now = now()->toDateTimeString();
        $values = [];
        $bindings = [];

        foreach ($lotBalanceKeys as $k) {
            $values[] = '(?, ?, ?, ?, 0.0000, 0.0000, ?, ?)';
            $bindings[] = $orgId;
            $bindings[] = $k['warehouse_id'];
            $bindings[] = $k['location_id'];
            $bindings[] = $k['lot_id'];
            $bindings[] = $now;
            $bindings[] = $now;
        }

        $sql = 'INSERT INTO stock_lot_balances (organization_id, warehouse_id, location_id, lot_id, on_hand, reserved, created_at, updated_at) VALUES '
            . implode(', ', $values)
            . ' ON CONFLICT (organization_id, warehouse_id, location_id, lot_id) DO NOTHING';

        DB::statement($sql, $bindings);
    }

    /**
     * Lock stock_lot_balances rows ordered strictly by id ASC.
     * Returns a Collection indexed by "warehouse_id:location_id:lot_id".
     */
    public function lockLotBalances(string $orgId, array $lotBalanceKeys): Collection
    {
        if (empty($lotBalanceKeys)) {
            return collect();
        }

        $query = StockLotBalance::where('organization_id', $orgId);

        $query->where(function ($q) use ($lotBalanceKeys) {
            foreach ($lotBalanceKeys as $k) {
                $q->orWhere(function ($sub) use ($k) {
                    $sub->where('warehouse_id', $k['warehouse_id'])
                        ->where('location_id', $k['location_id'])
                        ->where('lot_id', $k['lot_id']);
                });
            }
        });

        // Global deterministic lock order: ORDER BY id ASC
        $balances = $query->orderBy('id', 'asc')->lockForUpdate()->get();

        return $balances->keyBy(function (StockLotBalance $b) {
            return "{$b->warehouse_id}:{$b->location_id}:{$b->lot_id}";
        });
    }

    /**
     * Increment lot on_hand quantity using BCMath precision.
     */
    public function incrementOnHand(StockLotBalance $lotBalance, string $quantity): void
    {
        $newOnHand = bcadd((string) $lotBalance->on_hand, $quantity, 4);
        $lotBalance->update(['on_hand' => $newOnHand]);
    }

    /**
     * Decrement lot on_hand quantity with sufficiency and expiry checks.
     */
    public function decrementOnHand(StockLotBalance $lotBalance, string $quantity, bool $checkExpiry = true): void
    {
        $available = bcsub((string) $lotBalance->on_hand, (string) $lotBalance->reserved, 4);

        if (bccomp($available, $quantity, 4) < 0) {
            throw new StockDocumentException(
                "Insufficient lot stock for lot ID {$lotBalance->lot_id}. Available: {$available}, Required: {$quantity}",
                'INSUFFICIENT_LOT_STOCK',
                409
            );
        }

        if ($checkExpiry && $lotBalance->stockLot && $lotBalance->stockLot->expired_at) {
            $today = Carbon::today()->toDateString();
            $exp = Carbon::parse($lotBalance->stockLot->expired_at)->toDateString();
            if ($exp < $today) {
                throw new StockDocumentException(
                    "Lot '{$lotBalance->stockLot->lot_no}' is expired (expired_at: {$exp})",
                    'LOT_EXPIRED',
                    409
                );
            }
        }

        $newOnHand = bcsub((string) $lotBalance->on_hand, $quantity, 4);
        $lotBalance->update(['on_hand' => $newOnHand]);
    }

    /**
     * Set exact lot on_hand quantity (for Adjustments) with reserved stock check.
     */
    public function setOnHand(StockLotBalance $lotBalance, string $countedQuantity): void
    {
        if (bccomp($countedQuantity, (string) $lotBalance->reserved, 4) < 0) {
            throw new StockDocumentException(
                "Counted lot quantity ({$countedQuantity}) cannot be less than reserved lot stock ({$lotBalance->reserved})",
                'ADJUSTMENT_BELOW_RESERVED_STOCK',
                409
            );
        }

        $lotBalance->update(['on_hand' => $countedQuantity]);
    }

    /**
     * Increment reserved lot stock with available stock and expiry checks.
     */
    public function incrementReserved(StockLotBalance $lotBalance, string $quantity, bool $checkExpiry = true): void
    {
        $available = bcsub((string) $lotBalance->on_hand, (string) $lotBalance->reserved, 4);

        if (bccomp($available, $quantity, 4) < 0) {
            throw new StockDocumentException(
                "Insufficient lot stock for reservation of lot ID {$lotBalance->lot_id}. Available: {$available}, Required: {$quantity}",
                'INSUFFICIENT_LOT_STOCK',
                409
            );
        }

        if ($checkExpiry && $lotBalance->stockLot && $lotBalance->stockLot->expired_at) {
            $today = Carbon::today()->toDateString();
            $exp = Carbon::parse($lotBalance->stockLot->expired_at)->toDateString();
            if ($exp < $today) {
                throw new StockDocumentException(
                    "Cannot reserve expired lot '{$lotBalance->stockLot->lot_no}' (expired_at: {$exp})",
                    'LOT_EXPIRED',
                    409
                );
            }
        }

        $newReserved = bcadd((string) $lotBalance->reserved, $quantity, 4);
        $lotBalance->update(['reserved' => $newReserved]);
    }

    /**
     * Decrement reserved lot stock.
     */
    public function decrementReserved(StockLotBalance $lotBalance, string $quantity): void
    {
        if (bccomp((string) $lotBalance->reserved, $quantity, 4) < 0) {
            throw new StockDocumentException(
                "Reserved lot stock is less than released quantity for lot ID {$lotBalance->lot_id}. Reserved: {$lotBalance->reserved}, Releasing: {$quantity}",
                'RESERVATION_BALANCE_MISMATCH',
                409
            );
        }

        $newReserved = bcsub((string) $lotBalance->reserved, $quantity, 4);
        $lotBalance->update(['reserved' => $newReserved]);
    }

    /**
     * Consume reserved lot stock on Issue posting.
     */
    public function consumeReserved(StockLotBalance $lotBalance, string $quantity): void
    {
        if (bccomp((string) $lotBalance->on_hand, $quantity, 4) < 0 || bccomp((string) $lotBalance->reserved, $quantity, 4) < 0) {
            throw new StockDocumentException(
                "Cannot consume lot reservation: balance mismatch for lot ID {$lotBalance->lot_id}",
                'RESERVATION_BALANCE_MISMATCH',
                409
            );
        }

        $newOnHand = bcsub((string) $lotBalance->on_hand, $quantity, 4);
        $newReserved = bcsub((string) $lotBalance->reserved, $quantity, 4);
        $lotBalance->update(['on_hand' => $newOnHand, 'reserved' => $newReserved]);
    }
}
