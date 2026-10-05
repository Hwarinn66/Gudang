<?php

use App\Http\Controllers\AdjustmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/login', [AuthController::class, 'authenticate'])->middleware('throttle:20,1');
});
Route::middleware(['auth', 'auth.session'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::view('/scanner', 'scanner')->name('scanner');
    Route::get('/settings', [AuthController::class, 'settings'])->name('settings');
    Route::put('/settings/password', [AuthController::class, 'password'])->name('settings.password');
    Route::get('/products/export', [ProductController::class, 'export'])->name('products.export');
    Route::resource('products', ProductController::class)->except('destroy');
    Route::post('/products/{product}/active', [ProductController::class, 'active'])->name('products.active');
    Route::get('/products/{product}/photo', [ProductController::class, 'image'])->name('products.photo');
    Route::get('/products/{product}/qr', [ProductController::class, 'qr'])->name('products.qr');
    Route::get('/products/{product}/qr.svg', [ProductController::class, 'qrImage'])->name('products.qr-image');
    Route::post('/products/{product}/regenerate-qr', [ProductController::class, 'regenerate'])->name('products.regenerate');
    Route::get('/pending-transactions', [TransactionController::class, 'index'])->name('pending');
    Route::get('/history/{type}/export', [HistoryController::class, 'export'])->name('history.export');
    Route::get('/history/{type}', [HistoryController::class, 'index'])->name('history');
    Route::get('/stock-adjustments', [AdjustmentController::class, 'index'])->name('adjustments');
    Route::post('/stock-adjustments', [AdjustmentController::class, 'store'])->name('adjustments.store');
    Route::get('/locations', [LocationController::class, 'index'])->name('locations');
    Route::post('/locations/{kind}', [LocationController::class, 'store'])->name('locations.store');
    Route::post('/locations/{kind}/{id}/active', [LocationController::class, 'active'])->name('locations.active');
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    // Intentionally WEB middleware: session auth + CSRF also protect /api/*.
    Route::prefix('api')->name('api.')->group(function () {
        Route::post('/scan', [TransactionController::class, 'scan'])->middleware('throttle:60,1')->name('scan');
        Route::get('/pending-transactions', [TransactionController::class, 'pending'])->name('pending');
        Route::patch('/transactions/{transaction}/quantity', [TransactionController::class, 'quantity']);
        Route::post('/transactions/{transaction}/approve', [TransactionController::class, 'approve']);
        Route::post('/transactions/{transaction}/cancel', [TransactionController::class, 'cancel']);
        Route::get('/products/search',[ProductController::class, 'search'])->name('products.search');
    });
});
