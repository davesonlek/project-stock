<?php

namespace Modules\MasterData\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\AuthenticationAudit\Models\User;
use Modules\MasterData\Models\Brand;
use Modules\MasterData\Models\Category;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\GoodsSupplier;
use Modules\MasterData\Models\Product;
use Modules\MasterData\Models\Supplier;
use Modules\MasterData\Models\Unit;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;

class MasterDataDatabaseSeeder extends Seeder
{
    /**
     * Run the MasterData module database seeds.
     */
    public function run(): void
    {
        $organization = Organization::where('name', 'Global Supply Co.')->first();

        if (!$organization) {
            throw new \RuntimeException('Organization "Global Supply Co." not found. Please run AuthenticationAudit seeder first.');
        }

        $owner = User::where('email', 'owner@test.com')->first() ?? User::first();
        $userId = $owner->id;
        $orgId = $organization->id;

        // 1. Categories
        $categoriesData = ['Beverages', 'Dry Food', 'Frozen Food'];
        $categories = [];
        foreach ($categoriesData as $name) {
            $categories[$name] = Category::updateOrCreate(
                ['organization_id' => $orgId, 'name' => $name],
                ['is_active' => true, 'created_by' => $userId, 'updated_by' => $userId]
            );
        }

        // 2. Brands
        $brandsData = ['Coca Cola', 'Pepsi', 'Demo Brand'];
        $brands = [];
        foreach ($brandsData as $name) {
            $brands[$name] = Brand::updateOrCreate(
                ['organization_id' => $orgId, 'name' => $name],
                ['is_active' => true, 'created_by' => $userId, 'updated_by' => $userId]
            );
        }

        // 3. Units
        $unitsData = [
            ['code' => 'PCS', 'name' => 'Piece'],
            ['code' => 'BTL', 'name' => 'Bottle'],
            ['code' => 'PACK', 'name' => 'Pack'],
            ['code' => 'CTN', 'name' => 'Carton'],
        ];
        $units = [];
        foreach ($unitsData as $u) {
            $units[$u['code']] = Unit::updateOrCreate(
                ['organization_id' => $orgId, 'code' => $u['code']],
                ['name' => $u['name'], 'is_active' => true, 'created_by' => $userId, 'updated_by' => $userId]
            );
        }

        // 4. Products
        $product = Product::updateOrCreate(
            ['organization_id' => $orgId, 'sku' => 'BEV-COCA'],
            [
                'name' => 'Coca Cola Original',
                'barcode' => '885000000000',
                'description' => 'Carbonated Soft Drink',
                'category_id' => $categories['Beverages']->id,
                'brand_id' => $brands['Coca Cola']->id,
                'is_active' => true,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]
        );

        // 5. Goods (1 Normal, 1 Lot-tracked, 1 Serial-tracked)
        $goodsBottle = Goods::updateOrCreate(
            ['organization_id' => $orgId, 'product_id' => $product->id, 'unit_id' => $units['BTL']->id],
            [
                'barcode' => '885000000001',
                'pack_size' => 1.0,
                'cost' => 10.00,
                'sell_price' => 15.00,
                'is_lot_tracked' => false,
                'is_serial_tracked' => false,
                'is_active' => true,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]
        );

        $goodsPack = Goods::updateOrCreate(
            ['organization_id' => $orgId, 'product_id' => $product->id, 'unit_id' => $units['PACK']->id],
            [
                'barcode' => '885000000006',
                'pack_size' => 6.0,
                'cost' => 55.00,
                'sell_price' => 80.00,
                'is_lot_tracked' => true,
                'is_serial_tracked' => false,
                'is_active' => true,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]
        );

        $goodsCarton = Goods::updateOrCreate(
            ['organization_id' => $orgId, 'product_id' => $product->id, 'unit_id' => $units['CTN']->id],
            [
                'barcode' => '885000000024',
                'pack_size' => 24.0,
                'cost' => 200.00,
                'sell_price' => 300.00,
                'is_lot_tracked' => true,
                'is_serial_tracked' => true,
                'is_active' => true,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]
        );

        // 6. Suppliers
        $supplier = Supplier::updateOrCreate(
            ['organization_id' => $orgId, 'name' => 'Demo Beverage Supplier'],
            [
                'contact_person' => 'John Doe',
                'email' => 'beverage_supplier@test.com',
                'phone' => '02-1234567',
                'tax_id' => 'TAX-BEV-001',
                'address' => '123 Beverage Road, Industrial Estate',
                'is_active' => true,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]
        );

        // 7. Goods Suppliers Link
        GoodsSupplier::updateOrCreate(
            ['organization_id' => $orgId, 'supplier_id' => $supplier->id, 'goods_id' => $goodsBottle->id],
            [
                'supplier_sku' => 'SUP-BTL-01',
                'purchase_price' => 9.50,
                'lead_time_days' => 3,
                'is_primary' => true,
            ]
        );

        // 8. Warehouses
        $manager = User::where('email', 'manager@test.com')->first();
        $managerId = $manager?->id;

        $warehouseMain = Warehouse::updateOrCreate(
            ['organization_id' => $orgId, 'code' => 'MAIN'],
            [
                'name' => 'Main Warehouse',
                'address' => '100 Logistics Blvd, Warehouse District',
                'manager_id' => $managerId,
                'is_active' => true,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]
        );

        $warehouseCold = Warehouse::updateOrCreate(
            ['organization_id' => $orgId, 'code' => 'COLD'],
            [
                'name' => 'Cold Storage',
                'address' => '102 Logistics Blvd, Cold District',
                'manager_id' => $managerId,
                'is_active' => true,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]
        );

        // 9. Warehouse Locations
        $mainLocations = [
            ['code' => 'A-01-01', 'zone' => 'Zone A', 'aisle' => '01', 'rack' => '01', 'bin' => '01'],
            ['code' => 'A-01-02', 'zone' => 'Zone A', 'aisle' => '01', 'rack' => '01', 'bin' => '02'],
            ['code' => 'B-01-01', 'zone' => 'Zone B', 'aisle' => '01', 'rack' => '01', 'bin' => '01'],
        ];
        foreach ($mainLocations as $loc) {
            WarehouseLocation::updateOrCreate(
                ['organization_id' => $orgId, 'warehouse_id' => $warehouseMain->id, 'code' => $loc['code']],
                [
                    'name' => 'Location ' . $loc['code'],
                    'zone' => $loc['zone'],
                    'aisle' => $loc['aisle'],
                    'rack' => $loc['rack'],
                    'bin' => $loc['bin'],
                    'is_active' => true,
                ]
            );
        }

        $coldLocations = [
            ['code' => 'C-01-01', 'zone' => 'Zone C', 'aisle' => '01', 'rack' => '01', 'bin' => '01'],
            ['code' => 'C-01-02', 'zone' => 'Zone C', 'aisle' => '01', 'rack' => '01', 'bin' => '02'],
        ];
        foreach ($coldLocations as $loc) {
            WarehouseLocation::updateOrCreate(
                ['organization_id' => $orgId, 'warehouse_id' => $warehouseCold->id, 'code' => $loc['code']],
                [
                    'name' => 'Location ' . $loc['code'],
                    'zone' => $loc['zone'],
                    'aisle' => $loc['aisle'],
                    'rack' => $loc['rack'],
                    'bin' => $loc['bin'],
                    'is_active' => true,
                ]
            );
        }
    }
}
