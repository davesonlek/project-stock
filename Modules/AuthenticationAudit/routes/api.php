<?php

use Illuminate\Support\Facades\Route;
use Modules\AuthenticationAudit\Http\Controllers\AuthController;
use Modules\AuthenticationAudit\Http\Controllers\OrganizationController;
use Modules\AuthenticationAudit\Http\Middleware\JwtMiddleware;
use Modules\AuthenticationAudit\Http\Middleware\OrganizationContextMiddleware;

Route::prefix('v1')->group(function () {
    // Public Authentication
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login'])->name('auth.login');
    });

    // JWT Protected Routes
    Route::middleware([JwtMiddleware::class])->group(function () {
        Route::prefix('auth')->group(function () {
            Route::post('refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
            Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
            Route::get('me', [AuthController::class, 'me'])->name('auth.me');
        });

        // User's Organizations (List organizations user belongs to)
        Route::get('organizations', [OrganizationController::class, 'index'])->name('organizations.index');

        // Organization-Scoped Protected Routes
        Route::middleware([OrganizationContextMiddleware::class])->group(function () {
            Route::get('organizations/current', [OrganizationController::class, 'current'])->name('organizations.current');
        });
    });
});
