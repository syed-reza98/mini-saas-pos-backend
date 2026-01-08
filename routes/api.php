<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

// API Version 1
Route::prefix('v1')->group(function () {
    // Public authentication routes
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
    });

    // Protected routes (require authentication)
    Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
        // Auth routes
        Route::prefix('auth')->group(function () {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('logout-all', [AuthController::class, 'logoutAll']);
        });

        // Tenant-scoped routes (require X-Tenant-ID header)
        Route::middleware('tenant')->group(function () {
            // Products
            Route::apiResource('products', ProductController::class);
            Route::post('products/{product}/restore', [ProductController::class, 'restore'])
                ->withTrashed();

            // Customers
            Route::apiResource('customers', CustomerController::class);
            Route::post('customers/{customer}/restore', [CustomerController::class, 'restore'])
                ->withTrashed();

            // Orders
            Route::apiResource('orders', OrderController::class)->except(['update']);
            Route::patch('orders/{order}/status', [OrderController::class, 'update']);
            Route::post('orders/{order}/cancel', [OrderController::class, 'cancel']);

            // Reports
            Route::prefix('reports')->group(function () {
                Route::get('daily-sales', [ReportController::class, 'dailySales']);
                Route::get('top-selling-products', [ReportController::class, 'topSellingProducts']);
                Route::get('low-stock', [ReportController::class, 'lowStock']);
            });
        });
    });
});
