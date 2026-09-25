<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:5,1')->name('login.store');

    Route::middleware(EnsureUserIsAdmin::class)->group(function () {
        Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

        Route::redirect('/', '/admin/products')->name('home');

        Route::patch('/products/prices', [ProductController::class, 'updatePrices'])->name('products.prices.update');
        Route::resource('products', ProductController::class)->except(['show']);
    });
});
