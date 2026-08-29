<?php

namespace Modules\AuthenticationAudit\Policies;

use Modules\AuthenticationAudit\Enums\RoleEnum;
use Modules\AuthenticationAudit\Models\User;
use Modules\AuthenticationAudit\Services\OrganizationContext;

class AuditLogPolicy
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
        $role = $this->getRole();
        return in_array($role, [
            RoleEnum::OWNER->value,
            RoleEnum::ADMIN->value,
            RoleEnum::MANAGER->value,
        ], true);
    }
}
