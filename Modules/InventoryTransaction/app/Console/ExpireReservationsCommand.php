<?php

namespace Modules\InventoryTransaction\Console;

use Illuminate\Console\Command;
use Modules\InventoryTransaction\Services\ExpireStockReservationService;

class ExpireReservationsCommand extends Command
{
    protected $signature = 'stock:expire-reservations';
    protected $description = 'Scan and expire all overdue active stock reservations';

    public function handle(ExpireStockReservationService $expireService): int
    {
        $this->info('Scanning for overdue stock reservations...');
        $count = $expireService->expireAllOverdue();
        $this->info("Successfully expired {$count} overdue reservations.");

        return 0;
    }
}
