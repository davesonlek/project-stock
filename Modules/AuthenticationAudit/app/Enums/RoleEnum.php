<?php

namespace Modules\AuthenticationAudit\Enums;

enum RoleEnum: string
{
    case OWNER = 'OWNER';
    case ADMIN = 'ADMIN';
    case MANAGER = 'MANAGER';
    case STAFF = 'STAFF';
}
