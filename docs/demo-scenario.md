# 10-Minute Presentation & Demo Scenario

This guide outlines the recommended 10–15 minute live demo flow to showcase the architecture and key features of the prototype.

---

## 1. Demo Preparation
1. Reset environment to fresh demo state:
   ```bash
   php artisan migrate:fresh --seed
   npm run build
   php artisan serve
   ```
2. Open Browser at `http://127.0.0.1:8000`.

---

## 2. Step-by-Step Demo Flow

### Step 1: Login & RBAC Enforcement (2 mins)
1. Login as Staff (`staff@test.com` / `password123`).
2. Show the scoped staff menu. Attempt to click Approve/Post on any pending document; demonstrate that authorization is rejected server-side.
3. Switch login to Admin (`admin@test.com` / `password123`).
4. Show full administration dashboard with real-time KPI metrics (`total_available = on_hand - reserved`).

### Step 2: Master Data & Tenant Isolation (1 min)
1. Navigate to `/products` and `/goods`. Show standard, lot-tracked, and serial-tracked SKU setup.
2. Highlight that all master data is strictly scoped to `Global Supply Co.`.

### Step 3: Receive Shipment Workflow (2 mins)
1. Navigate to `/stock/documents` $\rightarrow$ **New Document**.
2. Select type `RECEIVE`, destination `Main Warehouse / A-01-01`.
3. Add Line: Goods = `Laptop Dell XPS 15`, Quantity = `100`.
4. Click **Submit** (Draft $\rightarrow$ Pending).
5. Click **Approve** (Pending $\rightarrow$ Approved). Show that stock is STILL 0.
6. Click **POST STOCK**.
7. Navigate to `/inventory/stock`: show `on_hand = 100`, `available = 100`.
8. Navigate to `/inventory/movements`: show immutable `RECEIVE +100` ledger entry.

### Step 4: Reservation & Consumption (2 mins)
1. Create `ISSUE` document for `20` units of `Laptop Dell XPS 15`.
2. Submit document $\rightarrow$ Click **Reserve Stock** on line 1.
3. Show `/inventory/stock`: `on_hand = 100`, `reserved = 20`, `available = 80`.
4. Show `/inventory/movements`: Verify **ZERO movements created during reservation**.
5. Approve and **POST STOCK**.
6. Show `/inventory/stock`: `on_hand = 80`, `reserved = 0`, `available = 80`.
7. Show `/inventory/movements`: `ISSUE -20` recorded; Reservation marked `CONSUMED`.

### Step 5: Transfer Operation (1 min)
1. Create `TRANSFER` document for `30` units from `A-01-01` to `A-01-02`.
2. Submit $\rightarrow$ Approve $\rightarrow$ POST.
3. Show `/inventory/stock`: `A-01-01 = 50`, `A-01-02 = 30` (Total System Stock conserved = `80`).
4. Show `/inventory/movements`: `TRANSFER_OUT -30` and `TRANSFER_IN +30`.

### Step 6: Compensating Reversal (2 mins)
1. Open the POSTED Issue document from Step 4.
2. Click **Reverse Document** $\rightarrow$ Enter reason: `Customer cancelled shipment`.
3. Show that original document is now `REVERSED`.
4. Show linked Reversal document in `POSTED` status.
5. Show `/inventory/movements`: Original `ISSUE -20` untouched; new `REVERSAL +20` appended. Net delta = `0`.

### Step 7: Immutability & Race Condition Proofs (3 mins)
1. Run `php artisan stock:verify-integrity` to demonstrate 100% mathematical ledger reconciliation.
2. Run Concurrency Script (`scripts/concurrency-demo.ps1` or `php artisan test --filter SystemConcurrencyStressTest`):
   - Demonstrates Stock = 10, Two parallel Issue 8 workers firing concurrently.
   - Proves exactly 1 worker succeeds, 1 worker is rejected with `INSUFFICIENT_AVAILABLE_STOCK`, and final stock is safely `2.0000` (zero negative stock, zero lost updates).
