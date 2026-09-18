<?php

use App\Http\Controllers\CommercialCatalogController as Commercial;
use App\Http\Middleware\PrivateCommerce;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/commercial')->middleware([PrivateCommerce::class, 'auth', 'auth.session', 'verified', 'can:view-catalog-admin'])->group(function () {
    Route::get('/suppliers', [Commercial::class, 'suppliers']);
    Route::get('/suppliers/{supplier}', [Commercial::class, 'supplier']);
    Route::get('/products/{product}', [Commercial::class, 'product']);
    Route::get('/rules', [Commercial::class, 'rules']);
    Route::middleware('can:manage-catalog')->group(function () {
        Route::post('/suppliers', [Commercial::class, 'saveSupplier']);
        Route::put('/suppliers/{supplier}', [Commercial::class, 'saveSupplier']);
        Route::post('/products/{product}/offers', [Commercial::class, 'saveOffer']);
        Route::put('/products/{product}/offers/{offer}', [Commercial::class, 'saveOffer']);
        Route::put('/products/{product}/settings', [Commercial::class, 'settings']);
        Route::post('/products/{product}/apply-price', [Commercial::class, 'apply']);
        Route::post('/rules', [Commercial::class, 'saveRule']);
        Route::put('/rules/{rule}', [Commercial::class, 'saveRule']);
        Route::put('/category-rule', [Commercial::class, 'assignRule']);
    });
});
