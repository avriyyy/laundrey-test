<?php

use App\Http\Controllers\Web\AuthWebController;
use App\Http\Controllers\Web\CustomerWebController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\OperationWebController;
use App\Http\Controllers\Web\OrderWebController;
use App\Http\Controllers\Web\ServiceWebController;
use App\Http\Controllers\Web\TrackWebController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TrackWebController::class, 'index'])->name('home');
Route::post('/track', [TrackWebController::class, 'track'])->name('track.search');

Route::redirect('/login', '/admin/login', 301);
Route::redirect('/dashboard', '/admin/dashboard', 301);

Route::prefix('admin')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthWebController::class, 'showLogin'])->name('login');
        Route::post('/login', [AuthWebController::class, 'login']);
    });

    Route::middleware('auth')->group(function () {
        Route::post('/logout', [AuthWebController::class, 'logout'])->name('logout');
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::middleware('role:admin')->group(function () {
            Route::resource('orders', OrderWebController::class);
            Route::resource('services', ServiceWebController::class)->except(['create', 'show', 'edit']);
            Route::resource('customers', CustomerWebController::class);
            Route::get('/customers-lookup', [CustomerWebController::class, 'lookup'])->name('customers.lookup');
        });

        Route::middleware('role:admin')->group(function () {
            Route::get('/operations', [OperationWebController::class, 'index'])->name('operations.index');
            Route::post('/operations/{order}/status', [OperationWebController::class, 'updateStatus'])->name('operations.status');
        });
    });
});
