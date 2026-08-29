<?php

use Illuminate\Support\Facades\Route;
use Modules\AuthenticationAudit\Http\Controllers\WebAuditLogController;
use Modules\AuthenticationAudit\Http\Controllers\WebAuthController;
use Modules\AuthenticationAudit\Http\Middleware\WebOrganizationContextMiddleware;
use Modules\InventoryTransaction\Http\Controllers\WebDashboardController;
use Modules\InventoryTransaction\Http\Controllers\WebReservationController;
use Modules\InventoryTransaction\Http\Controllers\WebSerialNumberController;
use Modules\InventoryTransaction\Http\Controllers\WebStockBalanceController;
use Modules\InventoryTransaction\Http\Controllers\WebStockDocumentController;
use Modules\InventoryTransaction\Http\Controllers\WebStockDocumentWorkflowController;
use Modules\InventoryTransaction\Http\Controllers\WebStockLotController;
use Modules\InventoryTransaction\Http\Controllers\WebStockMovementController;
use Modules\MasterData\Http\Controllers\WebBrandController;
use Modules\MasterData\Http\Controllers\WebCategoryController;
use Modules\MasterData\Http\Controllers\WebGoodsController;
use Modules\MasterData\Http\Controllers\WebProductController;
use Modules\MasterData\Http\Controllers\WebSupplierController;
use Modules\MasterData\Http\Controllers\WebUnitController;
use Modules\MasterData\Http\Controllers\WebWarehouseController;
use Modules\MasterData\Http\Controllers\WebWarehouseLocationController;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

// Guest Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [WebAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [WebAuthController::class, 'login'])->name('login.submit');
});

// Authenticated Routes (Pre-Organization Selection)
Route::middleware('auth')->group(function () {
    Route::post('/logout', [WebAuthController::class, 'logout'])->name('logout');
    Route::get('/organizations/select', [WebAuthController::class, 'showSelectOrg'])->name('organizations.select');
    Route::post('/organizations/select', [WebAuthController::class, 'selectOrg'])->name('organizations.select.submit');
});

// Authenticated + Organization Context Web Routes
Route::middleware(['auth', WebOrganizationContextMiddleware::class])->group(function () {
    // Dashboard
    Route::get('/dashboard', [WebDashboardController::class, 'index'])->name('dashboard');

    // Master Data Routes
    Route::prefix('categories')->name('categories.')->group(function () {
        Route::get('/', [WebCategoryController::class, 'index'])->name('index');
        Route::get('/create', [WebCategoryController::class, 'create'])->name('create');
        Route::post('/', [WebCategoryController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [WebCategoryController::class, 'edit'])->name('edit');
        Route::put('/{id}', [WebCategoryController::class, 'update'])->name('update');
        Route::post('/{id}/activate', [WebCategoryController::class, 'activate'])->name('activate');
        Route::post('/{id}/deactivate', [WebCategoryController::class, 'deactivate'])->name('deactivate');
    });

    Route::prefix('brands')->name('brands.')->group(function () {
        Route::get('/', [WebBrandController::class, 'index'])->name('index');
        Route::get('/create', [WebBrandController::class, 'create'])->name('create');
        Route::post('/', [WebBrandController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [WebBrandController::class, 'edit'])->name('edit');
        Route::put('/{id}', [WebBrandController::class, 'update'])->name('update');
        Route::post('/{id}/activate', [WebBrandController::class, 'activate'])->name('activate');
        Route::post('/{id}/deactivate', [WebBrandController::class, 'deactivate'])->name('deactivate');
    });

    Route::prefix('units')->name('units.')->group(function () {
        Route::get('/', [WebUnitController::class, 'index'])->name('index');
        Route::get('/create', [WebUnitController::class, 'create'])->name('create');
        Route::post('/', [WebUnitController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [WebUnitController::class, 'edit'])->name('edit');
        Route::put('/{id}', [WebUnitController::class, 'update'])->name('update');
        Route::post('/{id}/activate', [WebUnitController::class, 'activate'])->name('activate');
        Route::post('/{id}/deactivate', [WebUnitController::class, 'deactivate'])->name('deactivate');
    });

    Route::prefix('products')->name('products.')->group(function () {
        Route::get('/', [WebProductController::class, 'index'])->name('index');
        Route::get('/create', [WebProductController::class, 'create'])->name('create');
        Route::post('/', [WebProductController::class, 'store'])->name('store');
        Route::get('/{id}', [WebProductController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [WebProductController::class, 'edit'])->name('edit');
        Route::put('/{id}', [WebProductController::class, 'update'])->name('update');
        Route::post('/{id}/activate', [WebProductController::class, 'activate'])->name('activate');
        Route::post('/{id}/deactivate', [WebProductController::class, 'deactivate'])->name('deactivate');
    });

    Route::prefix('goods')->name('goods.')->group(function () {
        Route::get('/', [WebGoodsController::class, 'index'])->name('index');
        Route::get('/create', [WebGoodsController::class, 'create'])->name('create');
        Route::post('/', [WebGoodsController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [WebGoodsController::class, 'edit'])->name('edit');
        Route::put('/{id}', [WebGoodsController::class, 'update'])->name('update');
        Route::post('/{id}/activate', [WebGoodsController::class, 'activate'])->name('activate');
        Route::post('/{id}/deactivate', [WebGoodsController::class, 'deactivate'])->name('deactivate');
    });

    Route::prefix('suppliers')->name('suppliers.')->group(function () {
        Route::get('/', [WebSupplierController::class, 'index'])->name('index');
        Route::get('/create', [WebSupplierController::class, 'create'])->name('create');
        Route::post('/', [WebSupplierController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [WebSupplierController::class, 'edit'])->name('edit');
        Route::put('/{id}', [WebSupplierController::class, 'update'])->name('update');
        Route::post('/{id}/activate', [WebSupplierController::class, 'activate'])->name('activate');
        Route::post('/{id}/deactivate', [WebSupplierController::class, 'deactivate'])->name('deactivate');
    });

    Route::prefix('warehouses')->name('warehouses.')->group(function () {
        Route::get('/', [WebWarehouseController::class, 'index'])->name('index');
        Route::get('/create', [WebWarehouseController::class, 'create'])->name('create');
        Route::post('/', [WebWarehouseController::class, 'store'])->name('store');
        Route::get('/{id}', [WebWarehouseController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [WebWarehouseController::class, 'edit'])->name('edit');
        Route::put('/{id}', [WebWarehouseController::class, 'update'])->name('update');
        Route::post('/{id}/activate', [WebWarehouseController::class, 'activate'])->name('activate');
        Route::post('/{id}/deactivate', [WebWarehouseController::class, 'deactivate'])->name('deactivate');
    });

    Route::prefix('warehouse-locations')->name('warehouse-locations.')->group(function () {
        Route::get('/', [WebWarehouseLocationController::class, 'index'])->name('index');
        Route::get('/create', [WebWarehouseLocationController::class, 'create'])->name('create');
        Route::post('/', [WebWarehouseLocationController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [WebWarehouseLocationController::class, 'edit'])->name('edit');
        Route::put('/{id}', [WebWarehouseLocationController::class, 'update'])->name('update');
        Route::post('/{id}/activate', [WebWarehouseLocationController::class, 'activate'])->name('activate');
        Route::post('/{id}/deactivate', [WebWarehouseLocationController::class, 'deactivate'])->name('deactivate');
    });

    // Inventory Screens
    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::get('/stock', [WebStockBalanceController::class, 'index'])->name('stock');
        Route::get('/lots', [WebStockLotController::class, 'index'])->name('lots');
        Route::get('/serials', [WebSerialNumberController::class, 'index'])->name('serials');
        Route::get('/reservations', [WebReservationController::class, 'index'])->name('reservations');
        Route::post('/reservations/{id}/release', [WebReservationController::class, 'release'])->name('reservations.release');
        Route::get('/movements', [WebStockMovementController::class, 'index'])->name('movements');
    });

    // Stock Document Transactions & Workflow
    Route::prefix('stock/documents')->name('stock.documents.')->group(function () {
        Route::get('/', [WebStockDocumentController::class, 'index'])->name('index');
        Route::get('/create', [WebStockDocumentController::class, 'create'])->name('create');
        Route::post('/', [WebStockDocumentController::class, 'store'])->name('store');
        Route::get('/{id}', [WebStockDocumentController::class, 'show'])->name('show');
        Route::post('/{id}/lines', [WebStockDocumentController::class, 'addLine'])->name('lines.add');
        Route::delete('/{id}/lines/{lineId}', [WebStockDocumentController::class, 'deleteLine'])->name('lines.delete');

        // Workflow state changes
        Route::post('/{id}/submit', [WebStockDocumentWorkflowController::class, 'submit'])->name('submit');
        Route::post('/{id}/approve', [WebStockDocumentWorkflowController::class, 'approve'])->name('approve');
        Route::post('/{id}/cancel', [WebStockDocumentWorkflowController::class, 'cancel'])->name('cancel');
        Route::post('/{id}/lines/{lineId}/reserve', [WebStockDocumentWorkflowController::class, 'reserve'])->name('reserve');
        Route::post('/{id}/post', [WebStockDocumentWorkflowController::class, 'post'])->name('post');
        Route::post('/{id}/reverse', [WebStockDocumentWorkflowController::class, 'reverse'])->name('reverse');
    });

    // Audit Trail
    Route::prefix('audit-logs')->name('audit-logs.')->group(function () {
        Route::get('/', [WebAuditLogController::class, 'index'])->name('index');
        Route::get('/{id}', [WebAuditLogController::class, 'show'])->name('show');
    });
});
