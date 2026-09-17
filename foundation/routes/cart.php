<?php

use App\Http\Controllers\CartController;
use Illuminate\Support\Facades\Route;

// Public session routes. Authentication and email verification are not required.
Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
Route::post('/cart/items', [CartController::class, 'add'])->name('cart.add');
Route::patch('/cart/items/{line}', [CartController::class, 'update'])->whereUuid('line')->name('cart.update');
Route::delete('/cart/items/{line}', [CartController::class, 'remove'])->whereUuid('line')->name('cart.remove');
Route::delete('/cart', [CartController::class, 'clear'])->name('cart.clear');
