<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PasswordController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\PurchaseOrderController;
use App\Http\Controllers\Purchaser\PurchaseOrderController as PurchaserPurchaseOrderController;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsPurchaser;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:5,1')->name('login.store');

    Route::middleware(EnsureUserIsAdmin::class)->group(function () {
        Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

        Route::get('/password', [PasswordController::class, 'edit'])->name('password.edit');
        Route::put('/password', [PasswordController::class, 'update'])->middleware('throttle:5,1')->name('password.update');
        Route::get('/', [DashboardController::class, 'index'])->name('home');
        Route::get('/order-summary/export', [DashboardController::class, 'export'])->name('order-summary.export');
        Route::get('/orders-per-person/export', [DashboardController::class, 'exportPerPerson'])->name('orders-per-person.export');

        Route::resource('purchase-orders', PurchaseOrderController::class)
            ->parameters(['purchase-orders' => 'purchaseOrder'])->only(['index', 'create', 'store', 'show']);

        Route::get('/products/print', [ProductController::class, 'print'])->name('products.print');
        Route::patch('/products/prices', [ProductController::class, 'updatePrices'])->name('products.prices.update');
        Route::patch('/products/order', [ProductController::class, 'updateOrder'])->name('products.order.update');
        Route::resource('products', ProductController::class)->except(['show']);
    });
});

Route::prefix('purchaser')->name('purchaser.')->group(function () {
    Route::get('/login', [AuthController::class, 'createPurchaser'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:5,1')->name('login.store');

    Route::middleware(EnsureUserIsPurchaser::class)->group(function () {
        Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
        Route::get('/password', [PasswordController::class, 'edit'])->name('password.edit');
        Route::put('/password', [PasswordController::class, 'update'])->middleware('throttle:5,1')->name('password.update');
        Route::get('/', [PurchaserPurchaseOrderController::class, 'index'])->name('home');
        Route::get('/purchase-orders/{purchaseOrder}', [PurchaserPurchaseOrderController::class, 'show'])->name('purchase-orders.show');
        Route::put('/purchase-orders/{purchaseOrder}', [PurchaserPurchaseOrderController::class, 'update'])->name('purchase-orders.update');
    });
});
