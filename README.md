# Enterprise Stock Management Prototype (Laravel 12 + PostgreSQL)

An enterprise-grade stock management system prototype built with **Laravel 12**, **PostgreSQL**, **Modular Architecture (`nwidart/laravel-modules`)**, **JWT Authentication (`tymon/jwt-auth`)**, and **Blade + Bootstrap 5 UI**.

Designed to demonstrate **ACID transactional guarantees**, **PostgreSQL pessimistic locking (`SELECT FOR UPDATE`)**, **race-condition protection**, **immutable historical stock ledgers**, **multi-tenant organization isolation**, **granular RBAC**, **temporary stock reservations**, and **compensating reversals**.

---

## Key Architectural Features

- **ACID Transactional Engine:** Atomic updates across stock balances, lot balances, piece-level serial numbers, and immutable movement records within a single database transaction.
- **Race Condition Prevention:** Deterministic row-level pessimistic locking eliminates double-posting, lost updates, and negative inventory states under true concurrent loads.
- **Immutable Movement Ledger:** Historical stock movements are append-only; direct SQL `UPDATE` and `DELETE` queries are strictly prohibited via PostgreSQL PL/pgSQL database triggers.
- **Idempotency Defense:** Client-supplied `Idempotency-Key` headers ensure safe request replays without duplicate inventory deductions.
- **Stock Reservation Lifecycle:** Supports `ACTIVE` $\rightarrow$ `CONSUMED` / `RELEASED` / `EXPIRED` (`available = on_hand - reserved`).
- **Compensating Reversals:** Non-destructive transaction reversal generating linked reversal documents and offsetting movement entries.
- **Multi-Tenant Organization Isolation:** Request-scoped isolation prevents cross-tenant data leaks and unauthorized mutations.
- **Role-Based Access Control (RBAC):** Hierarchical permissions across `OWNER`, `ADMIN`, `MANAGER`, and `STAFF`.

---

## Technology Stack

- **Backend:** PHP 8.2+, Laravel 12
- **Database:** PostgreSQL 16+ (UUIDs, Check Constraints, Immutability Triggers)
- **Module System:** `nwidart/laravel-modules`
- **Authentication:** `tymon/jwt-auth` (API) & Session Cookies (Web UI)
- **Frontend:** Laravel Blade, Bootstrap 5.3, Bootstrap Icons, Vanilla JS, Vite 6
- **Testing:** PHPUnit / Pest with PostgreSQL connection

---

## Quick Start / Installation Guide

### 1. Clone & Install Dependencies
```bash
git clone <repository-url> project_stock
cd project_stock
composer install
npm install
```

### 2. Environment Setup
```bash
# Windows
copy .env.example .env

# Linux / macOS
cp .env.example .env
```

Configure your PostgreSQL database connection in `.env`:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=stock_phototype
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

### 3. Generate Keys & Seed Database
```bash
php artisan key:generate
php artisan jwt:secret
php artisan migrate:fresh --seed
npm run build
```

### 4. Run Application
```bash
php artisan serve
```
Open your browser at `http://127.0.0.1:8000`.

---

## Demo Accounts

| Role | Email | Password | Access Scope |
| :--- | :--- | :--- | :--- |
| **Owner** | `owner@test.com` | `password123` | Full system administration & Reversals |
| **Admin** | `admin@test.com` | `password123` | Full administration, Audit logs, Reversals |
| **Manager**| `manager@test.com`| `password123` | Master Data, Document Approval & Posting |
| **Staff** | `staff@test.com` | `password123` | Draft creation, Line input, Submissions |

> *Note: Demo credentials are for prototype demonstration only.*

---

## Automated Verification & Testing

Run the comprehensive test suite (164 automated tests with 100% pass rate):

```bash
# Run entire test suite
php artisan test

# Verify inventory mathematical reconciliation
php artisan stock:verify-integrity

# Run concurrency race-condition proof
php artisan test --filter SystemConcurrencyStressTest
```

---

## Documentation Directory

- [System Architecture](docs/architecture.md)
- [Database Schema & ERD](docs/database-architecture.md)
- [Stock State vs. Immutable Ledger](docs/stock-architecture.md)
- [Concurrency & Race Condition Proofs](docs/concurrency.md)
- [Transaction Flows & State Machine](docs/transaction-flow.md)
- [Security & Multi-Tenant Isolation](docs/security.md)
- [REST API Reference](docs/api.md)
- [10-Minute Presentation & Demo Scenario](docs/demo-scenario.md)
- [Automated Testing Guide](docs/testing.md)
- [Final Test Report](docs/final-test-report.md)
- [Known Limitations & Prototype Boundaries](docs/known-limitations.md)

---

## Postman API Collection

Import the pre-configured Postman collection and environment located in:
- `postman/Stock_Management_Prototype_API.postman_collection.json`
- `postman/Stock_Management_Prototype.postman_environment.json`
