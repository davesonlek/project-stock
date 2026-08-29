<?php

namespace Modules\InventoryTransaction\Services;

use Illuminate\Support\Collection;
use Modules\InventoryTransaction\Models\StockDocument;

class InventoryLockManager
{
    public function __construct(
        protected StockBalanceManager $balanceManager,
        protected StockLotManager $lotManager,
        protected SerialNumberManager $serialManager
    ) {}

    /**
     * Ensure and acquire all pessimistic row locks in deterministic ascending order.
     *
     * @return array{
     *     balances: Collection,
     *     lotBalances: Collection,
     *     serials: Collection
     * }
     */
    public function lockAllRequiredInventory(
        string $orgId,
        StockDocument $document,
        array $balanceKeys,
        array $lotBalanceKeys,
        array $serialNos = []
    ): array {
        // 1. Ensure required balance rows exist
        $this->balanceManager->ensureRows($orgId, $balanceKeys);

        // 2. Ensure required lot balance rows exist
        $this->lotManager->ensureRows($orgId, $lotBalanceKeys);

        // 3. Lock stock balances ordered by id ASC
        $balances = $this->balanceManager->lockBalances($orgId, $balanceKeys);

        // 4. Lock lot balances ordered by id ASC
        $lotBalances = $this->lotManager->lockLotBalances($orgId, $lotBalanceKeys);

        // 5. Lock existing serial numbers ordered by id ASC
        $serials = $this->serialManager->lockSerials($orgId, $serialNos);

        return [
            'balances' => $balances,
            'lotBalances' => $lotBalances,
            'serials' => $serials,
        ];
    }
}
