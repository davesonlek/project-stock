<?php

namespace Modules\InventoryTransaction\Console;

use Illuminate\Console\Command;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\AuthenticationAudit\Models\Role;
use Modules\AuthenticationAudit\Models\User;
use Modules\AuthenticationAudit\Models\UserOrganization;
use Modules\AuthenticationAudit\Services\OrganizationContext;
use Modules\InventoryTransaction\Exceptions\StockDocumentException;
use Modules\InventoryTransaction\Services\ReverseStockDocumentService;

class ReverseDocumentWorkerCommand extends Command
{
    protected $signature = 'stock:reverse-worker {documentId} {idempotencyKey} {userId} {orgId}';
    protected $description = 'Worker command to reverse a stock document in an isolated process';

    public function handle(): int
    {
        $documentId = $this->argument('documentId');
        $idempotencyKey = $this->argument('idempotencyKey');
        $userId = $this->argument('userId');
        $orgId = $this->argument('orgId');

        $user = User::findOrFail($userId);
        $org = Organization::findOrFail($orgId);
        $membership = UserOrganization::where('user_id', $userId)->where('organization_id', $orgId)->first();
        $role = $membership?->role ?? Role::where('code', 'ADMIN')->first();

        $context = new OrganizationContext($user, $org, $membership ?? new UserOrganization(), $role);
        app()->instance(OrganizationContext::class, $context);

        $reverseService = app(ReverseStockDocumentService::class);

        try {
            $result = $reverseService->execute($documentId, $idempotencyKey, ['reason' => 'Concurrent test reversal']);
            $this->line(json_encode([
                'success' => true,
                'code' => 'SUCCESS',
                'reversal_id' => $result['data']['id'] ?? null,
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
