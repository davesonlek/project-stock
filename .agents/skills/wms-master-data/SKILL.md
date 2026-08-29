---
name: wms-master-data
description: >-
  Guide for the MasterData Module in Laravel 12. Covers Categories, Brands, Units,
  Products (with Standard, Lot, and Serial tracking types), Suppliers, Warehouses, and Warehouse Locations.
---

# Master Data Module Guide (`Modules/MasterData`)

This skill describes the master data entities, classifications, hierarchy, and tracking configurations for the WMS.

---

## 1. Responsibilities

- **Product Classifications**: `Category`, `Brand`, `Unit` (Units of Measure).
- **Products / Goods**: SKU, Barcode, Name, Cost/Price, Weight/Dimensions, Tracking Type:
  - `STANDARD`: Standard bulk inventory by quantity.
  - `LOT_TRACKED`: Requires Lot Number & Expiry Date on every movement.
  - `SERIAL_TRACKED`: Requires unique Serial Number for each individual unit.
- **Suppliers**: Vendor master records and `ProductSupplier` sourcing catalogs.
- **Warehouses & Locations**:
  - `Warehouse`: Physical building or facility.
  - `WarehouseLocation`: Hierarchical storage coordinates (Zone, Aisle, Rack, Shelf, Bin).
  - Location Types: `RECEIVING`, `STORAGE`, `PICKING`, `STAGING`, `SHIPPING`, `DAMAGE`, `VIRTUAL`.

---

## 2. Directory Structure

```
Modules/MasterData/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── CategoryController.php
│   │   │   ├── UnitController.php
│   │   │   ├── ProductController.php
│   │   │   ├── SupplierController.php
│   │   │   └── WarehouseController.php
│   │   ├── Requests/
│   │   └── Resources/
│   ├── Models/
│   │   ├── Category.php
│   │   ├── Brand.php
│   │   ├── Unit.php
│   │   ├── Product.php
│   │   ├── Supplier.php
│   │   ├── ProductSupplier.php
│   │   ├── Warehouse.php
│   │   └── WarehouseLocation.php
│   ├── Enums/
│   │   ├── TrackingTypeEnum.php (STANDARD, LOT_TRACKED, SERIAL_TRACKED)
│   │   └── LocationTypeEnum.php
│   ├── Traits/
│   │   └── BelongsToOrganization.php
│   └── Services/
│       ├── ProductService.php
│       └── WarehouseLocationService.php
├── database/
│   ├── migrations/
│   └── seeders/
└── routes/
    ├── api.php
    └── web.php
```

---

## 3. Database Schema Overview

1. `categories` (id, organization_id, parent_id, code, name, is_active)
2. `brands` (id, organization_id, code, name)
3. `units` (id, organization_id, code, name, symbol, is_active)
4. `products` (id, organization_id, category_id, brand_id, unit_id, sku, barcode, name, description, tracking_type, is_active)
5. `suppliers` (id, organization_id, code, name, tax_id, email, phone, address, is_active)
6. `product_suppliers` (id, organization_id, product_id, supplier_id, supplier_sku, lead_time_days, cost_price)
7. `warehouses` (id, organization_id, code, name, address, is_active)
8. `warehouse_locations` (id, organization_id, warehouse_id, code, name, zone, aisle, rack, shelf, bin, location_type, is_pickable, is_active)

---

## 4. Multi-Tenant Scoping Rule
All Master Data models MUST use the `BelongsToOrganization` trait to ensure automatic filtering by the active `organization_id`.
