<?php

use App\Http\Controllers\Web\AuthWebController;
use App\Http\Controllers\Web\CustomerWebController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\OperationWebController;
use App\Http\Controllers\Web\OrderWebController;
use App\Http\Controllers\Web\ServiceWebController;
use App\Http\Controllers\Web\SettingWebController;
use App\Http\Controllers\Web\TenantWebController;
use App\Http\Controllers\Web\TrackWebController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TrackWebController::class, 'index'])->name('home');
Route::post('/track', [TrackWebController::class, 'track'])->name('track.search');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthWebController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthWebController::class, 'login']);
    Route::get('/register', [AuthWebController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthWebController::class, 'register']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthWebController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::middleware('role:admin')->group(function () {
        Route::get('/orders/{order}/invoice', [OrderWebController::class, 'invoice'])->name('orders.invoice');
        Route::get('/orders/{order}/invoice.pdf', [OrderWebController::class, 'invoicePdf'])->name('orders.invoice.pdf');
        Route::resource('orders', OrderWebController::class);
        Route::resource('services', ServiceWebController::class)->except(['create', 'show', 'edit']);
        Route::resource('customers', CustomerWebController::class);
        Route::get('/customers-lookup', [CustomerWebController::class, 'lookup'])->name('customers.lookup');
        Route::put('/settings', [SettingWebController::class, 'update'])->name('settings.update');
        Route::get('/operations', [OperationWebController::class, 'index'])->name('operations.index');
        Route::post('/operations/{order}/status', [OperationWebController::class, 'updateStatus'])->name('operations.status');
    });

    Route::middleware('role:superadmin')->group(function () {
        Route::prefix('admin')->name('admin.')->group(function () {
            Route::get('/dashboard', [DashboardController::class, 'platform'])->name('dashboard');
            Route::resource('tenants', TenantWebController::class)->only(['index', 'show', 'destroy']);
        });
    });
});
