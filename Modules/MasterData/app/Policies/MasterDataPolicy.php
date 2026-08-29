<?php

namespace Modules\MasterData\Policies;

use Modules\AuthenticationAudit\Enums\RoleEnum;
use Modules\AuthenticationAudit\Models\User;
use Modules\AuthenticationAudit\Services\OrganizationContext;

class MasterDataPolicy
{
    protected function getRole(): ?string
    {
        if (app()->bound(OrganizationContext::class)) {
            $context = app(OrganizationContext::class);
            $role = $context->roleCode();
            return is_string($role) ? $role : $role->value;
        }
        return null;
    }

    public function viewAny(User $user): bool
    {
        return $this->view($user);
    }

    public function view(User $user): bool
    {
        return in_array($this->getRole(), [
            RoleEnum::OWNER->value,
            RoleEnum::ADMIN->value,
            RoleEnum::MANAGER->value,
            RoleEnum::STAFF->value,
        ], true);
    }

    public function create(User $user): bool
    {
        return in_array($this->getRole(), [
            RoleEnum::OWNER->value,
            RoleEnum::ADMIN->value,
            RoleEnum::MANAGER->value,
        ], true);
    }

    public function update(User $user): bool
    {
        return in_array($this->getRole(), [
            RoleEnum::OWNER->value,
            RoleEnum::ADMIN->value,
            RoleEnum::MANAGER->value,
        ], true);
    }

    public function activate(User $user): bool
    {
        return in_array($this->getRole(), [
            RoleEnum::OWNER->value,
            RoleEnum::ADMIN->value,
            RoleEnum::MANAGER->value,
        ], true);
    }

    public function deactivate(User $user): bool
    {
        return in_array($this->getRole(), [
            RoleEnum::OWNER->value,
            RoleEnum::ADMIN->value,
            RoleEnum::MANAGER->value,
        ], true);
    }

    public function delete(User $user): bool
    {
        return in_array($this->getRole(), [
            RoleEnum::OWNER->value,
            RoleEnum::ADMIN->value,
        ], true);
    }
}
