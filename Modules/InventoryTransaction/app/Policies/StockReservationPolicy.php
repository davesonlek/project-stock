<?php

namespace Modules\InventoryTransaction\Policies;

use Modules\AuthenticationAudit\Enums\RoleEnum;
use Modules\AuthenticationAudit\Models\User;
use Modules\AuthenticationAudit\Models\UserOrganization;
use Modules\AuthenticationAudit\Services\OrganizationContext;
use Modules\InventoryTransaction\Models\StockReservation;

class StockReservationPolicy
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

    public function view(User $user, ?StockReservation $reservation = null): bool
    {
        return in_array($this->getRole($user), [
            RoleEnum::OWNER->value,
            RoleEnum::ADMIN->value,
            RoleEnum::MANAGER->value,
            RoleEnum::STAFF->value,
        ], true);
    }

    public function reserve(User $user): bool
    {
        return in_array($this->getRole($user), [
            RoleEnum::OWNER->value,
            RoleEnum::ADMIN->value,
            RoleEnum::MANAGER->value,
        ], true);
    }

    public function release(User $user, ?StockReservation $reservation = null): bool
    {
        return in_array($this->getRole($user), [
            RoleEnum::OWNER->value,
            RoleEnum::ADMIN->value,
            RoleEnum::MANAGER->value,
        ], true);
    }
}
