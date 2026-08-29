---
name: wms-architecture
description: >-
  System-wide architecture guide for the Laravel 12 WMS Prototype. Covers request flow,
  ACID transactions, pessimistic locking, immutable stock ledger, idempotency, and audit trails.
---

# WMS Core Architecture Guide

This skill provides architectural guidance and code design patterns for the Warehouse Management System (WMS) Prototype in Laravel 12.

---

## 1. High-Level Request Pipeline

```
Web / PDA / API
       │
       ▼
Authentication (JWT / Session)
       │
       ▼
Organization Context (Sets active organization_id)
       │
       ▼
RBAC / Policy (Verifies role permissions in active org)
       │
       ▼
Idempotency Middleware (Locks Idempotency-Key, avoids duplicate execution)
       │
       ▼
FormRequest (Payload validation)
       │
       ▼
Controller (Dispatches to Use Case Service)
       │
       ▼
Use Case Service
       ├── DB::transaction (ACID Boundary)
       ├── Pessimistic Lock (lockForUpdate on StockBalance / Lot / Serial)
       ├── Business Validation (Stock sufficiency, tracking integrity)
       ├── Balance Update (Increment/Decrement on_hand, reserved, available)
       ├── Lot / Serial Update (Lot balance deduction, serial status transitions)
       ├── Immutable Movement Ledger (Insert into stock_movements)
       └── Audit Log (Insert into audit_logs)
       │
       ▼
Database Commit (PostgreSQL / SQLite)
       │
       ▼
API Resource / Response (JSON)
```

---

## 2. Core Architectural Pillars

### 1. Multi-Organization Multi-Tenancy
- Every master data and transaction model MUST include `organization_id`.
- Handled globally via `BelongsToOrganization` trait and `OrganizationContextMiddleware`.
- `X-Organization-ID` HTTP header or Session determines the active organization context.

### 2. ACID Transaction & Pessimistic Locking
- All inventory balance modifications MUST be wrapped in `DB::transaction(function() { ... })`.
- Use `->lockForUpdate()` when reading balances to prevent race conditions during concurrent receipts or dispatches:
```php
$balance = StockBalance::where('organization_id', $orgId)
    ->where('warehouse_id', $warehouseId)
    ->where('location_id', $locationId)
    ->where('product_id', $productId)
    ->lockForUpdate()
    ->first();
```

### 3. Immutable Stock Ledger (Append-Only Movement Log)
- The table `stock_movements` is strictly append-only.
- Never update or delete rows in `stock_movements`.
- To reverse a posted document or movement, write a compensating `REVERSAL` row with negated `quantity_delta`.

### 4. Idempotency Key Handling
- `Idempotency-Key` header prevents network retries or double clicks from executing duplicate stock operations.
- Stored in `idempotency_keys` table with request hash, response body, and status (`PROCESSING`, `COMPLETED`, `FAILED`).

### 5. Audit Trail
- Model events and business actions are recorded in `audit_logs` table (user_id, organization_id, action, auditable_type, auditable_id, old_values, new_values, ip_address, user_agent).
