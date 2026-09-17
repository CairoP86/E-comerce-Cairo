<?php

use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\OrderAdminController;
use App\Http\Middleware\PrivateCommerce;
use Illuminate\Support\Facades\Route;

Route::middleware(PrivateCommerce::class)->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:20,1')->name('checkout.store');
    Route::get('/checkout/confirmation/{number}', [CheckoutController::class, 'confirmation'])->name('checkout.confirmation');
    Route::middleware(['auth', 'auth.session', 'verified', 'can:access-operations'])->prefix('admin/orders')->group(function () {
        Route::get('/', [OrderAdminController::class, 'index'])->name('admin.orders');
        Route::get('/{number}', [OrderAdminController::class, 'show'])->name('admin.orders.show');
    });
});
