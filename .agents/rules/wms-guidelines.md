# WMS Architecture & Engineering Guidelines

This document outlines the non-negotiable architectural constraints and engineering standards for the WMS Prototype in Laravel 12.

## 1. Multi-Organization Scoping
- Every business entity (MasterData, InventoryTransaction, AuditLog) MUST belong to an `organization_id`.
- The active organization is resolved from the session or `X-Organization-ID` header via `OrganizationContextMiddleware`.
- Never perform queries or updates without scoping by `organization_id`.

## 2. ACID Transactions & Concurrency Safety
- ALL inventory operations (Receive, Issue, Transfer, Adjustment, Reservation, Reversal) MUST execute inside `DB::transaction()`.
- Use **Pessimistic Locking (`lockForUpdate()`)** when reading or modifying `StockBalance`, `LotBalance`, and `SerialNumber` records:
  ```php
  $balance = StockBalance::where('organization_id', $orgId)
      ->where('warehouse_id', $warehouseId)
      ->where('location_id', $locationId)
      ->where('product_id', $productId)
      ->lockForUpdate()
      ->first();
  ```
- Balance updates must prevent negative `on_hand` or `available` quantities unless explicitly permitted by business policy (default: strict disallowance).

## 3. Immutable Stock Ledger (Append-Only)
- Never update or delete rows from `stock_movements`.
- Every stock balance change MUST be accompanied by an append-only row in `stock_movements` capturing:
  - `organization_id`, `warehouse_id`, `location_id`, `product_id`
  - `lot_id` / `lot_number`, `serial_number` (if applicable)
  - `document_type`, `document_id`, `document_line_id`
  - `movement_type` (`IN`, `OUT`, `TRANSFER_IN`, `TRANSFER_OUT`, `ADJUST_IN`, `ADJUST_OUT`, `REVERSAL`)
  - `quantity_delta` (positive or negative)
  - `balance_before`, `balance_after`
  - `created_by`, `created_at`
- Cancellations/Reversals of posted documents MUST write compensating reversal entries into `stock_movements`, never modifying past records.

## 4. Idempotency & Audit Trail
- Non-idempotent endpoints (POST/PUT/PATCH transactions) MUST support the `Idempotency-Key` header.
- Repeated requests with the same key within the TTL must return the cached response without re-executing business logic.
- All state-changing actions MUST write an entry to `audit_logs` capturing actor, action, model, and changes.

## 5. Modular Boundaries
- Keep code organized within `Modules/`:
  - `Modules/AuthenticationAudit`: Auth, RBAC, Organizations, Audit Trail, Idempotency.
  - `Modules/MasterData`: Products, Units, Categories, Brands, Warehouses, Locations, Suppliers.
  - `Modules/InventoryTransaction`: Balances, Documents, Document Lines, Serials, Lots, Reservations, Movements.
