---
name: wms-auth-audit
description: >-
  Guide for the AuthenticationAudit Module in Laravel 12. Covers User Authentication (JWT/Session),
  Multi-Organization membership, RBAC roles and permissions, Audit Trail, and Idempotency Keys.
---

# Authentication & Audit Module Guide (`Modules/AuthenticationAudit`)

This skill describes the structure, domain models, services, and middleware for the `AuthenticationAudit` module.

---

## 1. Responsibilities

- **Authentication**: JWT Token / Session authentication.
- **Multi-Organization**: Users can belong to multiple Organizations via `OrganizationUser` with roles per organization.
- **RBAC**: Role-based access control with granular `permissions` assigned to `roles`.
- **Audit Trail**: Append-only logging of user actions and entity state changes in `audit_logs`.
- **Idempotency**: Middleware and storage in `idempotency_keys` to ensure non-duplicated requests.

---

## 2. Directory Structure

```
Modules/AuthenticationAudit/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AuthController.php
│   │   │   ├── OrganizationController.php
│   │   │   └── RolePermissionController.php
│   │   ├── Middleware/
│   │   │   ├── SetOrganizationContext.php
│   │   │   ├── CheckPermission.php
│   │   │   └── EnforceIdempotency.php
│   │   ├── Requests/
│   │   └── Resources/
│   ├── Models/
│   │   ├── User.php
│   │   ├── Organization.php
│   │   ├── OrganizationUser.php
│   │   ├── Role.php
│   │   ├── Permission.php
│   │   ├── AuditLog.php
│   │   └── IdempotencyKey.php
│   ├── Services/
│   │   ├── AuthService.php
│   │   ├── OrganizationContextService.php
│   │   ├── RbacService.php
│   │   ├── AuditService.php
│   │   └── IdempotencyService.php
│   ├── Enums/
│   │   └── RoleEnum.php
│   └── Exceptions/
├── database/
│   ├── migrations/
│   └── seeders/
└── routes/
    ├── api.php
    └── web.php
```

---

## 3. Database Schema Overview

1. `users` (id, name, email, password, default_organization_id, is_active)
2. `organizations` (id, code, name, tax_id, is_active)
3. `organization_users` (id, user_id, organization_id, is_default, is_active)
4. `roles` (id, organization_id [null for system roles], name, slug, description)
5. `permissions` (id, module, slug, name, description)
6. `role_permissions` (role_id, permission_id)
7. `user_roles` (user_id, organization_id, role_id)
8. `audit_logs` (id, organization_id, user_id, action, auditable_type, auditable_id, old_values, new_values, ip_address, user_agent, created_at)
9. `idempotency_keys` (id, organization_id, user_id, key, request_hash, status, response_code, response_body, expires_at, created_at, updated_at)

---

## 4. Key Middleware Implementation

- **`SetOrganizationContext`**:
  Reads `X-Organization-ID` header or user's active session. Validates that the authenticated user belongs to that organization. Binds active organization to `app(OrganizationContextService::class)`.
- **`CheckPermission`**:
  Verifies if the user has the required permission slug within the active organization.
- **`EnforceIdempotency`**:
  Checks for `Idempotency-Key` header on POST/PUT requests. If present and completed, returns cached response immediately.
