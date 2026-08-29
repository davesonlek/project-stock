# Stock Architecture: State vs. Immutable Ledger

## 1. Operational State vs. Historical Ledger

A fundamental principle of this system is the strict architectural separation between **Operational State** and **Immutable Historical Ledger**:

| Dimension | `stock_balances` / `stock_lot_balances` | `stock_movements` |
| :--- | :--- | :--- |
| **Purpose** | High-performance operational state queries & availability checks | Legal, financial, compliance audit trail and historical reconstruction |
| **Mutation Mode** | `UPDATE` in-place under pessimistic row lock (`lockForUpdate()`) | `INSERT` only (Append-only) |
| **Immutability** | Mutable state | **Immutable** (enforced by PostgreSQL PL/pgSQL triggers) |
| **Key Formula** | `available = on_hand - reserved` | $\sum(\text{quantity\_delta}) = \text{on\_hand}$ |

---

## 2. Invariant Formulas

1. **Available Stock Formula:**
   $$\text{available} = \text{on\_hand} - \text{reserved}$$
   - When **Reserving Stock:** `on_hand` is unchanged, `reserved` increases by $Q$, and `available` decreases by $Q$. Zero stock movement is created.
   - When **Posting Reserved Issue:** `on_hand` decreases by $Q$, `reserved` decreases by $Q$, and `available` remains unchanged. An `ISSUE` movement of $-Q$ is recorded.
   - When **Releasing Reservation:** `on_hand` is unchanged, `reserved` decreases by $Q$, and `available` increases by $Q$.

2. **Ledger Balance Reconciliation:**
   $$\text{stock\_balances.on\_hand} = \sum_{\text{location, goods}} \text{stock\_movements.quantity\_delta}$$

3. **Lot Balance Invariant:**
   $$\text{stock\_balances.on\_hand} = \sum_{\text{lots}} \text{stock\_lot\_balances.on\_hand}$$

4. **Serial Invariant:**
   $$\text{stock\_balances.on\_hand} = \text{COUNT}(\text{status} \in \{\text{IN\_STOCK}, \text{RESERVED}\})$$
