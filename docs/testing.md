# Automated Testing & Verification Guide

## 1. Running the Automated Test Suites

```bash
# Run entire test suite (164 tests)
php artisan test

# Run Concurrency & Race Condition tests
php artisan test --filter Concurrency

# Run Security & Immutability tests
php artisan test --filter Security

# Run Ledger Reconciliation tests
php artisan test --filter Reconciliation

# Run Web UI & Feature tests
php artisan test --filter Web
```

---

## 2. Test Group Architecture

| Test Suite | Purpose | Key Assertions |
| :--- | :--- | :--- |
| `SystemConcurrencyStressTest` | True multi-process race condition test | Stock=10, Two Issue 8 $\rightarrow$ 1 Success, 1 Insufficient, Final Stock=2 |
| `SystemLedgerReconciliationTest`| Invariant & mathematical reconciliation | $\sum(\text{Movements}) == \text{on\_hand}$, Zero orphaned records |
| `SystemSecurityAndImmutabilityTest`| PostgreSQL DB trigger & constraint verification| Direct SQL `UPDATE`/`DELETE` on movements blocked; Check constraints verified |
| `SystemPerformanceBenchmarkTest` | Query counts & EXPLAIN ANALYZE review | Zero N+1 query explosion, Index utilization |
| `StockDocumentWebTest` | Complete Web UI lifecycle | Draft $\rightarrow$ Line Add $\rightarrow$ Submit $\rightarrow$ Approve $\rightarrow$ Post $\rightarrow$ Reverse |
