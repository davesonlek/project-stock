<?php

namespace Modules\AuthenticationAudit\Services;

use Modules\AuthenticationAudit\Enums\RoleEnum;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\AuthenticationAudit\Models\Role;
use Modules\AuthenticationAudit\Models\User;
use Modules\AuthenticationAudit\Models\UserOrganization;

class OrganizationContext
{
    public function __construct(
        protected User $user,
        protected Organization $organization,
        protected UserOrganization $membership,
        protected Role $role
    ) {}

    public function user(): User
    {
        return $this->user;
    }

    public function userId(): string
    {
        return $this->user->id;
    }

    public function organization(): Organization
    {
        return $this->organization;
    }

    public function organizationId(): string
    {
        return $this->organization->id;
    }

    public function membership(): UserOrganization
    {
        return $this->membership;
    }

    public function role(): Role
    {
        return $this->role;
    }

    public function roleCode(): RoleEnum|string
    {
        return RoleEnum::tryFrom($this->role->code) ?? $this->role->code;
    }

    public function isOwner(): bool
    {
        return $this->role->code === RoleEnum::OWNER->value;
    }

    public function isAdmin(): bool
    {
        return $this->role->code === RoleEnum::ADMIN->value;
    }

    public function isManager(): bool
    {
        return $this->role->code === RoleEnum::MANAGER->value;
    }

    public function isStaff(): bool
    {
        return $this->role->code === RoleEnum::STAFF->value;
    }
}
