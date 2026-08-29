<?php

namespace Modules\InventoryTransaction\Policies;

use Modules\AuthenticationAudit\Enums\RoleEnum;
use Modules\AuthenticationAudit\Models\User;
use Modules\AuthenticationAudit\Models\UserOrganization;
use Modules\AuthenticationAudit\Services\OrganizationContext;
use Modules\InventoryTransaction\Enums\StockDocumentStatus;
use Modules\InventoryTransaction\Models\StockDocument;

class StockDocumentPolicy
{
    protected function getRole(?User $user = null): ?string
    {
        if (app()->bound(OrganizationContext::class)) {
            $context = app(OrganizationContext::class);
            if (!$user || $context->userId() === $user->id) {
                $role = $context->roleCode();
                return is_string($role) ? $role : $role->value;
            }
        }

        if ($user) {
            $orgId = request()->header('X-Organization-Id');
            if ($orgId) {
                $membership = UserOrganization::where('user_id', $user->id)
                    ->where('organization_id', $orgId)
                    ->with('role')
                    ->first();
                if ($membership && $membership->role) {
                    return $membership->role->code;
                }
            }
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return in_array($this->getRole($user), [
            RoleEnum::OWNER->value,
            RoleEnum::ADMIN->value,
            RoleEnum::MANAGER->value,
            RoleEnum::STAFF->value,
        ], true);
    }

    public function view(User $user, ?StockDocument $document = null): bool
    {
        return in_array($this->getRole($user), [
            RoleEnum::OWNER->value,
            RoleEnum::ADMIN->value,
            RoleEnum::MANAGER->value,
            RoleEnum::STAFF->value,
        ], true);
    }

    public function create(User $user): bool
    {
        return in_array($this->getRole($user), [
            RoleEnum::OWNER->value,
            RoleEnum::ADMIN->value,
            RoleEnum::MANAGER->value,
            RoleEnum::STAFF->value,
        ], true);
    }

    public function update(User $user, ?StockDocument $document = null): bool
    {
        $role = $this->getRole($user);

        if (in_array($role, [RoleEnum::OWNER->value, RoleEnum::ADMIN->value, RoleEnum::MANAGER->value], true)) {
            return true;
        }

        if ($role === RoleEnum::STAFF->value) {
            return $document === null || $document->created_by === $user->id;
        }

        return false;
    }

    public function addLine(User $user, ?StockDocument $document = null): bool
    {
        return $this->update($user, $document);
    }

    public function updateLine(User $user, ?StockDocument $document = null): bool
    {
        return $this->update($user, $document);
    }

    public function deleteLine(User $user, ?StockDocument $document = null): bool
    {
        return $this->update($user, $document);
    }

    public function submit(User $user, ?StockDocument $document = null): bool
    {
        $role = $this->getRole($user);

        if (in_array($role, [RoleEnum::OWNER->value, RoleEnum::ADMIN->value, RoleEnum::MANAGER->value], true)) {
            return true;
        }

        if ($role === RoleEnum::STAFF->value) {
            return $document === null || $document->created_by === $user->id;
        }

        return false;
    }

    public function approve(User $user, ?StockDocument $document = null): bool
    {
        return in_array($this->getRole($user), [
            RoleEnum::OWNER->value,
            RoleEnum::ADMIN->value,
            RoleEnum::MANAGER->value,
        ], true);
    }

    public function cancel(User $user, ?StockDocument $document = null): bool
    {
        $role = $this->getRole($user);

        if (in_array($role, [RoleEnum::OWNER->value, RoleEnum::ADMIN->value, RoleEnum::MANAGER->value], true)) {
            return true;
        }

        if ($role === RoleEnum::STAFF->value) {
            if ($document === null) {
                return true;
            }
            $isDraft = $document->status === StockDocumentStatus::DRAFT || $document->status === 'DRAFT';
            return $isDraft && $document->created_by === $user->id;
        }

        return false;
    }

    public function post(User $user, ?StockDocument $document = null): bool
    {
        return in_array($this->getRole($user), [
            RoleEnum::OWNER->value,
            RoleEnum::ADMIN->value,
            RoleEnum::MANAGER->value,
        ], true);
    }

    public function reverse(User $user, ?StockDocument $document = null): bool
    {
        return in_array($this->getRole($user), [
            RoleEnum::OWNER->value,
            RoleEnum::ADMIN->value,
        ], true);
    }
}
