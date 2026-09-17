<?php

use App\Http\Controllers\CatalogImageController;
use App\Http\Controllers\CatalogProductController;
use App\Http\Controllers\CatalogTaxonomyController;
use App\Http\Controllers\PublicCatalogController;
use Illuminate\Support\Facades\Route;

Route::get('/catalog', [PublicCatalogController::class, 'index'])->name('catalog.index');
Route::get('/catalog/{slug}', [PublicCatalogController::class, 'show'])->name('catalog.show');
Route::get('/catalog-images/{image}', [CatalogImageController::class, 'show'])->name('catalog.image');

Route::prefix('admin/catalog')->name('admin.catalog.')->middleware(['auth', 'auth.session', 'verified', 'can:view-catalog-admin'])->group(function () {
    Route::get('/products', [CatalogProductController::class, 'index'])->name('products.index');
    Route::get('/products/create', [CatalogProductController::class, 'form'])->middleware('can:manage-catalog')->name('products.create');
    Route::get('/products/{product}/edit', [CatalogProductController::class, 'form'])->name('products.edit');
    Route::get('/{kind}', [CatalogTaxonomyController::class, 'index'])->whereIn('kind', ['categories', 'brands'])->name('taxonomy.index');
    Route::middleware('can:manage-catalog')->group(function () {
        Route::post('/products', [CatalogProductController::class, 'store'])->name('products.store');
        Route::put('/products/{product}', [CatalogProductController::class, 'update'])->name('products.update');
        Route::patch('/products/{product}/status', [CatalogProductController::class, 'status'])->name('products.status');
        Route::post('/products/{product}/images', [CatalogImageController::class, 'store'])->name('images.store');
        Route::patch('/products/{product}/images', [CatalogImageController::class, 'update'])->name('images.update');
        Route::delete('/products/{product}/images/{image}', [CatalogImageController::class, 'archive'])->name('images.archive');
        Route::post('/{kind}', [CatalogTaxonomyController::class, 'save'])->whereIn('kind', ['categories', 'brands'])->name('taxonomy.store');
        Route::put('/{kind}/{id}', [CatalogTaxonomyController::class, 'save'])->whereIn('kind', ['categories', 'brands'])->whereNumber('id')->name('taxonomy.update');
    });
});
