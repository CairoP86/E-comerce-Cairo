<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\InfoPageController;
use App\Http\Controllers\OperationsPanelController;
use App\Http\Controllers\PublicCatalogController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

require __DIR__.'/catalog.php';
require __DIR__.'/cart.php';
require __DIR__.'/checkout.php';
require __DIR__.'/commercial.php';

Route::get('/', [PublicCatalogController::class, 'home'])->name('home');

Route::get('/metodos-de-pago', [InfoPageController::class, 'payments'])->name('pages.payments');
Route::get('/venta-corporativa', [InfoPageController::class, 'corporate'])->name('pages.corporate');

Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => Inertia::render('auth/Login'))->name('login');
    Route::get('/register', fn () => Inertia::render('auth/Register'))->name('register');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth-actions');
    Route::get('/forgot-password', fn () => Inertia::render('auth/ForgotPassword'))->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'forgot'])->middleware('throttle:auth-actions')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'reset'])->middleware('throttle:auth-actions')->name('password.update');
});

Route::middleware(['auth', 'auth.session'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/verify-email', fn () => Inertia::render('auth/VerifyEmail'))->name('verification.notice');
    Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [AuthController::class, 'resend'])->middleware('throttle:auth-actions')->name('verification.send');
    Route::middleware('verified')->group(function () {
        Route::get('/account', fn () => Inertia::render('account/Overview'))->name('account');
        Route::get('/admin', OperationsPanelController::class)->middleware('can:access-operations')->name('admin');
        Route::get('/admin/audit', AuditLogController::class)->middleware('can:view-audit')->name('admin.audit');
    });
});
