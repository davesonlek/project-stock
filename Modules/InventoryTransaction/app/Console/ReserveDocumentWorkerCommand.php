<?php

namespace Modules\InventoryTransaction\Console;

use Illuminate\Console\Command;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\AuthenticationAudit\Models\Role;
use Modules\AuthenticationAudit\Models\User;
use Modules\AuthenticationAudit\Models\UserOrganization;
use Modules\AuthenticationAudit\Services\OrganizationContext;
use Modules\InventoryTransaction\Exceptions\StockDocumentException;
use Modules\InventoryTransaction\Services\ReserveStockService;

class ReserveDocumentWorkerCommand extends Command
{
    protected $signature = 'stock:reserve-worker {documentId} {lineId} {userId} {orgId}';
    protected $description = 'Worker command to reserve stock in an isolated process';

    public function handle(): int
    {
        $documentId = $this->argument('documentId');
        $lineId = (int) $this->argument('lineId');
        $userId = $this->argument('userId');
        $orgId = $this->argument('orgId');

        $user = User::findOrFail($userId);
        $org = Organization::findOrFail($orgId);
        $membership = UserOrganization::where('user_id', $userId)->where('organization_id', $orgId)->first();
        $role = $membership?->role ?? Role::where('code', 'MANAGER')->first();

        $context = new OrganizationContext($user, $org, $membership ?? new UserOrganization(), $role);
        app()->instance(OrganizationContext::class, $context);

        $reserveService = app(ReserveStockService::class);

        try {
            $reservation = $reserveService->execute($documentId, $lineId);
            $this->line(json_encode([
                'success' => true,
                'code' => 'SUCCESS',
                'reservation_id' => (string) $reservation->id,
            ]));
            return 0;
        } catch (StockDocumentException $e) {
            $this->line(json_encode([
                'success' => false,
                'code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ]));
            return 1;
        } catch (\Throwable $e) {
            $this->line(json_encode([
                'success' => false,
                'code' => 'EXCEPTION',
                'message' => $e->getMessage(),
            ]));
            return 1;
        }
    }
}
