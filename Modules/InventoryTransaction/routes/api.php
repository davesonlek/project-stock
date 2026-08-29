<?php

use Illuminate\Support\Facades\Route;
use Modules\AuthenticationAudit\Http\Middleware\JwtMiddleware;
use Modules\AuthenticationAudit\Http\Middleware\OrganizationContextMiddleware;
use Modules\InventoryTransaction\Http\Controllers\StockDocumentController;
use Modules\InventoryTransaction\Http\Controllers\StockDocumentLineController;
use Modules\InventoryTransaction\Http\Controllers\StockDocumentWorkflowController;
use Modules\InventoryTransaction\Http\Controllers\StockReservationController;

Route::prefix('v1')->middleware([JwtMiddleware::class, OrganizationContextMiddleware::class])->group(function () {
    // Document CRUD
    Route::get('stock/documents', [StockDocumentController::class, 'index'])->name('stock.documents.index');
    Route::post('stock/documents', [StockDocumentController::class, 'store'])->name('stock.documents.store');
    Route::get('stock/documents/{document}', [StockDocumentController::class, 'show'])->name('stock.documents.show');
    Route::match(['put', 'patch'], 'stock/documents/{document}', [StockDocumentController::class, 'update'])->name('stock.documents.update');

    // Document Lines CRUD
    Route::get('stock/documents/{document}/lines', [StockDocumentLineController::class, 'index'])->name('stock.documents.lines.index');
    Route::post('stock/documents/{document}/lines', [StockDocumentLineController::class, 'store'])->name('stock.documents.lines.store');
    Route::match(['put', 'patch'], 'stock/documents/{document}/lines/{line}', [StockDocumentLineController::class, 'update'])->name('stock.documents.lines.update');
    Route::delete('stock/documents/{document}/lines/{line}', [StockDocumentLineController::class, 'destroy'])->name('stock.documents.lines.destroy');

    // Document Workflow State Transitions
    Route::post('stock/documents/{document}/submit', [StockDocumentWorkflowController::class, 'submit'])->name('stock.documents.submit');
    Route::post('stock/documents/{document}/approve', [StockDocumentWorkflowController::class, 'approve'])->name('stock.documents.approve');
    Route::post('stock/documents/{document}/cancel', [StockDocumentWorkflowController::class, 'cancel'])->name('stock.documents.cancel');
    Route::post('stock/documents/{document}/post', [StockDocumentWorkflowController::class, 'post'])->name('stock.documents.post');
    Route::post('stock/documents/{document}/reverse', [StockDocumentWorkflowController::class, 'reverse'])->name('stock.documents.reverse');

    // Stock Reservations
    Route::post('stock/documents/{document}/lines/{line}/reserve', [StockReservationController::class, 'reserve'])->name('stock.reservations.reserve');
    Route::post('stock/reservations/{reservation}/release', [StockReservationController::class, 'release'])->name('stock.reservations.release');
    Route::get('stock/reservations', [StockReservationController::class, 'index'])->name('stock.reservations.index');
    Route::get('stock/reservations/{reservation}', [StockReservationController::class, 'show'])->name('stock.reservations.show');
});
