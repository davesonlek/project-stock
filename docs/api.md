# REST API Reference Guide

## 1. Authentication & Tenant Headers

Every protected API request requires:
- `Authorization: Bearer <jwt_token>`
- `X-Organization-Id: <organization_uuid>`

For mutations that mutate inventory (`/post`, `/reverse`):
- `Idempotency-Key: <unique_client_uuid>`

---

## 2. API Endpoints

### A. Authentication
| Method | Endpoint | Access | Description |
| :--- | :--- | :--- | :--- |
| `POST` | `/api/v1/auth/login` | Public | Authenticate user & return JWT token + accessible organizations |
| `POST` | `/api/v1/auth/logout` | Auth | Invalidate current JWT token |
| `POST` | `/api/v1/auth/refresh` | Auth | Refresh expired JWT token |
| `GET` | `/api/v1/auth/me` | Auth | Get authenticated user profile & active memberships |

### B. Master Data
| Method | Endpoint | Required Role | Description |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/products` | `STAFF+` | List products with pagination & filtering |
| `POST` | `/api/v1/products` | `MANAGER+` | Create a new product |
| `GET` | `/api/v1/goods` | `STAFF+` | List goods / SKUs |
| `POST` | `/api/v1/goods` | `MANAGER+` | Create SKU with lot/serial tracking flags |
| `GET` | `/api/v1/warehouses` | `STAFF+` | List warehouses |
| `GET` | `/api/v1/warehouse-locations`| `STAFF+` | List warehouse locations |

### C. Stock Documents & Workflow
| Method | Endpoint | Required Role | Description |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/stock/documents` | `STAFF+` | List documents with type & status filters |
| `POST` | `/api/v1/stock/documents` | `STAFF+` | Create DRAFT document header |
| `POST` | `/api/v1/stock/documents/{id}/lines` | `STAFF+` | Add item line to DRAFT document |
| `POST` | `/api/v1/stock/documents/{id}/submit` | `STAFF+` | Submit document (DRAFT $\rightarrow$ PENDING) |
| `POST` | `/api/v1/stock/documents/{id}/approve` | `MANAGER+` | Approve document (PENDING $\rightarrow$ APPROVED) |
| `POST` | `/api/v1/stock/documents/{id}/cancel` | Policy | Cancel document & auto-release active reservations |
| `POST` | `/api/v1/stock/documents/{id}/lines/{lineId}/reserve` | `MANAGER+` | Reserve available stock for an issue line |
| `POST` | `/api/v1/stock/documents/{id}/post` | `MANAGER+` | Execute atomic stock posting (Idempotent) |
| `POST` | `/api/v1/stock/documents/{id}/reverse` | `ADMIN+` | Execute compensating reversal (Idempotent) |

### D. Inventory Queries & Movement Ledger
| Method | Endpoint | Required Role | Description |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/inventory/stock` | `STAFF+` | Real-time on_hand, reserved, available balances |
| `GET` | `/api/v1/inventory/lots` | `STAFF+` | Lot balances with expiry tracking |
| `GET` | `/api/v1/inventory/serials` | `STAFF+` | Piece-level serial status and location |
| `GET` | `/api/v1/inventory/movements` | `STAFF+` | Immutable chronological stock movement ledger |

---

## 3. Standardized Business Error Codes

| Error Code | HTTP Status | Description |
| :--- | :---: | :--- |
| `UNAUTHENTICATED` | 401 | Missing or invalid JWT Bearer token |
| `ORGANIZATION_REQUIRED` | 400 | Missing `X-Organization-Id` header |
| `ORGANIZATION_ACCESS_DENIED` | 403 | User does not belong to requested tenant |
| `FORBIDDEN` | 403 | User role lacks required permission |
| `INVALID_DOCUMENT_STATE` | 422 | Requested transition not allowed from current state |
| `INSUFFICIENT_AVAILABLE_STOCK` | 422 | Requested quantity exceeds `on_hand - reserved` |
| `INSUFFICIENT_LOT_STOCK` | 422 | Requested quantity exceeds available lot stock |
| `LOT_EXPIRED` | 422 | Selected lot has expired |
| `SERIAL_NUMBER_ALREADY_EXISTS`| 409 | Serial already exists in stock |
| `DOCUMENT_ALREADY_POSTED` | 409 | Document was already posted |
| `DOCUMENT_ALREADY_REVERSED` | 409 | Document was already reversed |
| `IDEMPOTENCY_KEY_CONFLICT` | 409 | Reusing same key with different payload/document |
| `REVERSAL_INSUFFICIENT_STOCK` | 422 | Insufficient stock at location to reverse receive/transfer |
