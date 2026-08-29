---
name: wms-inventory-transaction
description: >-
  Comprehensive guide for the InventoryTransaction Module in Laravel 12. Covers Stock Balances,
  Lot Tracking, Serial Numbers, Document Workflows (Receive, Issue, Transfer, Adjustment, Reservation, Reversal),
  Posting State Machine, Pessimistic Locking, and Immutable Stock Ledger.
---

# Inventory Transaction Module Guide (`Modules/InventoryTransaction`)

This skill describes the transaction engine, stock balance aggregation, lot/serial tracking, document workflow state machine, and ledger logging.

---

## 1. Responsibilities

- **Stock Balances**:
  - `StockBalance`: Total on-hand, reserved, and available quantity per `(organization_id, warehouse_id, location_id, product_id)`.
  - `LotBalance`: Quantity breakdown per `(organization_id, warehouse_id, location_id, product_id, lot_number, expiry_date)`.
  - `SerialNumber`: State of individual tracked units (`IN_STOCK`, `RESERVED`, `ISSUED`, `DAMAGED`).
  - `StockReservation`: Soft-allocation of quantities tied to orders/documents.
- **Stock Documents**:
  - Document Types: `RECEIVE` (GRN), `ISSUE` (GI), `TRANSFER` (IT), `ADJUSTMENT` (ADJ), `RESERVATION` (RES), `REVERSAL` (REV).
  - Status Lifecycle: `DRAFT` ➔ `SUBMITTED` ➔ `POSTED` (or `CANCELLED` / `REVERSED`).
- **Immutable Movement Ledger**:
  - `StockMovement`: Append-only chronological audit log of every stock increment or decrement with before/after balances.

---

## 2. Directory Structure

```
Modules/InventoryTransaction/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── StockDocumentController.php
│   │   │   ├── StockBalanceController.php
│   │   │   └── StockMovementController.php
│   │   ├── Requests/
│   │   └── Resources/
│   ├── Models/
│   │   ├── StockDocument.php
│   │   ├── StockDocumentLine.php
│   │   ├── StockBalance.php
│   │   ├── LotBalance.php
│   │   ├── SerialNumber.php
│   │   ├── StockReservation.php
│   │   └── StockMovement.php
│   ├── Enums/
│   │   ├── DocumentTypeEnum.php
│   │   ├── DocumentStatusEnum.php
│   │   ├── MovementTypeEnum.php
│   │   └── SerialStatusEnum.php
│   ├── Services/
│   │   ├── StockPostingService.php
│   │   ├── StockBalanceService.php
│   │   ├── Strategies/
│   │   │   ├── ReceivePostingStrategy.php
│   │   │   ├── IssuePostingStrategy.php
│   │   │   ├── TransferPostingStrategy.php
│   │   │   ├── AdjustmentPostingStrategy.php
│   │   │   ├── ReservationPostingStrategy.php
│   │   │   └── ReversalPostingStrategy.php
│   │   └── Validation/
│   │       └── StockSufficiencyValidator.php
├── database/
│   ├── migrations/
│   └── seeders/
└── routes/
    ├── api.php
    └── web.php
```

---

## 3. Stock Posting & Concurrency Safety Rules

1. **Transaction Wrapping**:
   All balance updates, lot updates, serial status transitions, and ledger insertions MUST run inside `DB::transaction()`.
2. **Pessimistic Locking**:
   Always execute `StockBalance::lockForUpdate()` for target location and product before validating stock and applying deltas.
3. **Ledger Immutability**:
   Every change MUST create a new record in `stock_movements`. The math MUST be strictly verifiable:
   $$\text{balance\_after} = \text{balance\_before} + \text{quantity\_delta}$$
4. **Document Reversal Protocol**:
   When reversing a `POSTED` document:
   - Change document status to `REVERSED`.
   - Create a linked reversal document or reversal ledger entries with opposite signs.
   - Never delete original records.
