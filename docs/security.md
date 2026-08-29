# Security & Multi-Tenant Isolation Architecture

## 1. Authentication & Multi-Tenant Context
- **Web Sessions & JWT:** API requests use JWT Bearer tokens (`tymon/jwt-auth`); Web application uses secure HTTP-only session cookies.
- **Tenant Context (`OrganizationContext`):** Every authenticated request resolves the active tenant from `X-Organization-Id` (API) or session `current_organization_id` (Web).
- **Tenant Guarding:** If a user attempts to supply an organization ID where they lack active membership, the request is immediately rejected with `403 ORGANIZATION_ACCESS_DENIED`.
- **Information Leakage Prevention:** Querying a resource belonging to another organization returns a clean `404 Not Found` (never `403 Belongs to Organization A`).

---

## 2. Role-Based Access Control (RBAC) Matrix

| Action | `OWNER` | `ADMIN` | `MANAGER` | `STAFF` |
| :--- | :---: | :---: | :---: | :---: |
| **View Master Data & Balances** | ✓ | ✓ | ✓ | ✓ |
| **Create / Edit Master Data** | ✓ | ✓ | ✓ | ✗ |
| **Create / Edit Draft Document** | ✓ | ✓ | ✓ | ✓ |
| **Submit Document** | ✓ | ✓ | ✓ | ✓ |
| **Approve Document** | ✓ | ✓ | ✓ | ✗ |
| **Cancel Document** | ✓ | ✓ | ✓ | ✗ (if not creator) |
| **Reserve Stock** | ✓ | ✓ | ✓ | ✗ |
| **Post Stock Document** | ✓ | ✓ | ✓ | ✗ |
| **Reverse Stock Document** | ✓ | ✓ | ✗ | ✗ |
| **View Audit Trail Logs** | ✓ | ✓ | ✗ | ✗ |

---

## 3. Database-Level Defense & Immutability
1. **PostgreSQL Immutability Triggers:**
   - Applied to `stock_movements` and `audit_logs`.
   - Any raw SQL `UPDATE` or `DELETE` statement triggers a database exception: `ERROR: stock_movements is immutable. Modifications and deletions are strictly prohibited.`
2. **PostgreSQL Check Constraints:**
   - `stock_balances`: `on_hand >= 0`, `reserved >= 0`, `reserved <= on_hand`
   - `stock_lot_balances`: `on_hand >= 0`, `reserved >= 0`, `reserved <= on_hand`
3. **Password Security:**
   - Stored strictly with Bcrypt hashes (`$2y$`).
   - Audit logs filter out passwords, tokens, secrets, and authorization headers.
