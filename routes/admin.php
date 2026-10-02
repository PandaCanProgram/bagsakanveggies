<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:5,1')->name('login.store');

    Route::middleware(EnsureUserIsAdmin::class)->group(function () {
        Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

        Route::get('/', [DashboardController::class, 'index'])->name('home');
        Route::get('/order-summary/export', [DashboardController::class, 'export'])->name('order-summary.export');
        Route::get('/orders-per-person/export', [DashboardController::class, 'exportPerPerson'])->name('orders-per-person.export');

        Route::get('/products/print', [ProductController::class, 'print'])->name('products.print');
        Route::patch('/products/prices', [ProductController::class, 'updatePrices'])->name('products.prices.update');
        Route::patch('/products/order', [ProductController::class, 'updateOrder'])->name('products.order.update');
        Route::resource('products', ProductController::class)->except(['show']);
    });
});
