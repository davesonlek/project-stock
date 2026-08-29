# Database Architecture & Entity-Relationship Model

## 1. Relational Schema Summary

The database uses PostgreSQL native features including UUID primary keys, composite unique constraints, PostgreSQL CHECK constraints, and PL/pgSQL database triggers for immutability.

### A. Authentication & Organization
- `users`: User credentials, status, profile
- `organizations`: Tenant entities (UUID primary keys)
- `roles`: Role definitions (`OWNER`, `ADMIN`, `MANAGER`, `STAFF`)
- `user_organizations`: Pivot table binding User + Organization + Role
- `audit_logs`: Immutable security audit trail with pre/post JSON diffs

### B. Master Data
- `categories`, `brands`, `units`: Product taxonomies and UoM
- `products`: Abstract catalog items
- `goods`: Concrete SKUs with tracking flags (`is_lot_tracked`, `is_serial_tracked`)
- `suppliers`, `goods_suppliers`: Vendor associations
- `warehouses`, `warehouse_locations`: Physical hierarchy

### C. Inventory Operations & Ledger
- `stock_balances`: Operational state (`on_hand`, `reserved`, `available = on_hand - reserved`)
- `stock_lots`, `stock_lot_balances`: Lot batches, expiration dates, lot operational balances
- `serial_numbers`: Piece-level serial tracking with status (`IN_STOCK`, `RESERVED`, `ISSUED`, `REVERSED`, `DAMAGED`)
- `stock_documents`, `stock_document_lines`, `stock_document_line_serials`: Workflow transaction definitions
- `stock_reservations`: Temporary allocated stock holding
- `stock_movements`: Append-only immutable historical stock ledger (protected by DB trigger)
- `idempotency_keys`: Unique transaction request keys with response cache

---

## 2. Entity-Relationship Diagram (ERD)

```mermaid
erDiagram
    ORGANIZATION ||--o{ USER_ORGANIZATION : has
    USER ||--o{ USER_ORGANIZATION : member_of
    ROLE ||--o{ USER_ORGANIZATION : assigns
    
    ORGANIZATION ||--o{ PRODUCT : owns
    PRODUCT ||--o{ GOODS : defines
    
    ORGANIZATION ||--o{ WAREHOUSE : owns
    WAREHOUSE ||--o{ WAREHOUSE_LOCATION : contains
    
    GOODS ||--o{ STOCK_BALANCE : tracks
    WAREHOUSE_LOCATION ||--o{ STOCK_BALANCE : stores
    
    GOODS ||--o{ STOCK_LOT : batches
    STOCK_LOT ||--o{ STOCK_LOT_BALANCE : tracks
    WAREHOUSE_LOCATION ||--o{ STOCK_LOT_BALANCE : stores
    
    GOODS ||--o{ SERIAL_NUMBER : tracks
    WAREHOUSE_LOCATION ||--o{ SERIAL_NUMBER : stores
    
    ORGANIZATION ||--o{ STOCK_DOCUMENT : creates
    STOCK_DOCUMENT ||--o{ STOCK_DOCUMENT_LINE : contains
    GOODS ||--o{ STOCK_DOCUMENT_LINE : references
    
    STOCK_DOCUMENT ||--o{ STOCK_RESERVATION : reserves
    STOCK_DOCUMENT ||--o{ STOCK_MOVEMENT : generates
    GOODS ||--o{ STOCK_MOVEMENT : moves
    WAREHOUSE_LOCATION ||--o{ STOCK_MOVEMENT : locations
```
