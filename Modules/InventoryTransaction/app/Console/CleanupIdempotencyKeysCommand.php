<?php

namespace Modules\InventoryTransaction\Console;

use Illuminate\Console\Command;
use Modules\InventoryTransaction\Enums\IdempotencyStatus;
use Modules\InventoryTransaction\Models\IdempotencyKey;

class CleanupIdempotencyKeysCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'stock:cleanup-idempotency {--force : Force cleanup without confirmation}';

    /**
     * The console command description.
     */
    protected $description = 'Clean up expired idempotency keys from the database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Cleaning up expired idempotency keys...');

        $deletedCount = IdempotencyKey::where('expires_at', '<', now())
            ->where('status', '!=', IdempotencyStatus::PROCESSING->value)
            ->delete();

        $this->info("Successfully cleaned up {$deletedCount} expired idempotency key(s).");

        return self::SUCCESS;
    }
}
