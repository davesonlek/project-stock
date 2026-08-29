<?php

use Illuminate\Support\Facades\Route;
use Modules\AuthenticationAudit\Http\Middleware\JwtMiddleware;
use Modules\AuthenticationAudit\Http\Middleware\OrganizationContextMiddleware;
use Modules\MasterData\Http\Controllers\BrandController;
use Modules\MasterData\Http\Controllers\CategoryController;
use Modules\MasterData\Http\Controllers\GoodsController;
use Modules\MasterData\Http\Controllers\GoodsSupplierController;
use Modules\MasterData\Http\Controllers\ProductController;
use Modules\MasterData\Http\Controllers\SupplierController;
use Modules\MasterData\Http\Controllers\UnitController;
use Modules\MasterData\Http\Controllers\WarehouseController;
use Modules\MasterData\Http\Controllers\WarehouseLocationController;

Route::prefix('v1')->middleware([JwtMiddleware::class, OrganizationContextMiddleware::class])->group(function () {
    // Categories
    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::get('categories/{id}', [CategoryController::class, 'show'])->name('categories.show');
    Route::match(['put', 'patch'], 'categories/{id}', [CategoryController::class, 'update'])->name('categories.update');
    Route::post('categories/{id}/activate', [CategoryController::class, 'activate'])->name('categories.activate');
    Route::post('categories/{id}/deactivate', [CategoryController::class, 'deactivate'])->name('categories.deactivate');
    Route::delete('categories/{id}', [CategoryController::class, 'destroy'])->name('categories.destroy');

    // Brands
    Route::get('brands', [BrandController::class, 'index'])->name('brands.index');
    Route::post('brands', [BrandController::class, 'store'])->name('brands.store');
    Route::get('brands/{id}', [BrandController::class, 'show'])->name('brands.show');
    Route::match(['put', 'patch'], 'brands/{id}', [BrandController::class, 'update'])->name('brands.update');
    Route::post('brands/{id}/activate', [BrandController::class, 'activate'])->name('brands.activate');
    Route::post('brands/{id}/deactivate', [BrandController::class, 'deactivate'])->name('brands.deactivate');
    Route::delete('brands/{id}', [BrandController::class, 'destroy'])->name('brands.destroy');

    // Units
    Route::get('units', [UnitController::class, 'index'])->name('units.index');
    Route::post('units', [UnitController::class, 'store'])->name('units.store');
    Route::get('units/{id}', [UnitController::class, 'show'])->name('units.show');
    Route::match(['put', 'patch'], 'units/{id}', [UnitController::class, 'update'])->name('units.update');
    Route::post('units/{id}/activate', [UnitController::class, 'activate'])->name('units.activate');
    Route::post('units/{id}/deactivate', [UnitController::class, 'deactivate'])->name('units.deactivate');
    Route::delete('units/{id}', [UnitController::class, 'destroy'])->name('units.destroy');

    // Products
    Route::get('products', [ProductController::class, 'index'])->name('products.index');
    Route::post('products', [ProductController::class, 'store'])->name('products.store');
    Route::get('products/{id}', [ProductController::class, 'show'])->name('products.show');
    Route::match(['put', 'patch'], 'products/{id}', [ProductController::class, 'update'])->name('products.update');
    Route::post('products/{id}/activate', [ProductController::class, 'activate'])->name('products.activate');
    Route::post('products/{id}/deactivate', [ProductController::class, 'deactivate'])->name('products.deactivate');
    Route::delete('products/{id}', [ProductController::class, 'destroy'])->name('products.destroy');

    // Goods
    Route::get('goods', [GoodsController::class, 'index'])->name('goods.index');
    Route::post('goods', [GoodsController::class, 'store'])->name('goods.store');
    Route::get('goods/{id}', [GoodsController::class, 'show'])->name('goods.show');
    Route::match(['put', 'patch'], 'goods/{id}', [GoodsController::class, 'update'])->name('goods.update');
    Route::post('goods/{id}/activate', [GoodsController::class, 'activate'])->name('goods.activate');
    Route::post('goods/{id}/deactivate', [GoodsController::class, 'deactivate'])->name('goods.deactivate');
    Route::delete('goods/{id}', [GoodsController::class, 'destroy'])->name('goods.destroy');

    // Suppliers
    Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
    Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
    Route::get('suppliers/{id}', [SupplierController::class, 'show'])->name('suppliers.show');
    Route::match(['put', 'patch'], 'suppliers/{id}', [SupplierController::class, 'update'])->name('suppliers.update');
    Route::post('suppliers/{id}/activate', [SupplierController::class, 'activate'])->name('suppliers.activate');
    Route::post('suppliers/{id}/deactivate', [SupplierController::class, 'deactivate'])->name('suppliers.deactivate');
    Route::delete('suppliers/{id}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');

    // Goods Suppliers
    Route::get('goods-suppliers', [GoodsSupplierController::class, 'index'])->name('goods-suppliers.index');
    Route::post('goods-suppliers', [GoodsSupplierController::class, 'store'])->name('goods-suppliers.store');
    Route::get('goods-suppliers/{id}', [GoodsSupplierController::class, 'show'])->name('goods-suppliers.show');
    Route::match(['put', 'patch'], 'goods-suppliers/{id}', [GoodsSupplierController::class, 'update'])->name('goods-suppliers.update');
    Route::delete('goods-suppliers/{id}', [GoodsSupplierController::class, 'destroy'])->name('goods-suppliers.destroy');

    // Warehouses
    Route::get('warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');
    Route::post('warehouses', [WarehouseController::class, 'store'])->name('warehouses.store');
    Route::get('warehouses/{id}', [WarehouseController::class, 'show'])->name('warehouses.show');
    Route::match(['put', 'patch'], 'warehouses/{id}', [WarehouseController::class, 'update'])->name('warehouses.update');
    Route::post('warehouses/{id}/activate', [WarehouseController::class, 'activate'])->name('warehouses.activate');
    Route::post('warehouses/{id}/deactivate', [WarehouseController::class, 'deactivate'])->name('warehouses.deactivate');
    Route::delete('warehouses/{id}', [WarehouseController::class, 'destroy'])->name('warehouses.destroy');
    Route::get('warehouses/{warehouse}/locations', [WarehouseController::class, 'locations'])->name('warehouses.locations');

    // Warehouse Locations
    Route::get('warehouse-locations', [WarehouseLocationController::class, 'index'])->name('warehouse-locations.index');
    Route::post('warehouse-locations', [WarehouseLocationController::class, 'store'])->name('warehouse-locations.store');
    Route::get('warehouse-locations/{id}', [WarehouseLocationController::class, 'show'])->name('warehouse-locations.show');
    Route::match(['put', 'patch'], 'warehouse-locations/{id}', [WarehouseLocationController::class, 'update'])->name('warehouse-locations.update');
    Route::post('warehouse-locations/{id}/activate', [WarehouseLocationController::class, 'activate'])->name('warehouse-locations.activate');
    Route::post('warehouse-locations/{id}/deactivate', [WarehouseLocationController::class, 'deactivate'])->name('warehouse-locations.deactivate');
    Route::delete('warehouse-locations/{id}', [WarehouseLocationController::class, 'destroy'])->name('warehouse-locations.destroy');
});
