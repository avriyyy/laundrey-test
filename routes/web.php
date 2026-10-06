<?php

use App\Http\Controllers\Web\AuthWebController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\MyOrderWebController;
use App\Http\Controllers\Web\OperationWebController;
use App\Http\Controllers\Web\OrderWebController;
use App\Http\Controllers\Web\ServiceWebController;
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
        Route::resource('orders', OrderWebController::class);
        Route::resource('services', ServiceWebController::class)->except(['create', 'show', 'edit']);
    });

    Route::middleware('role:pelanggan')->group(function () {
        Route::get('/my-orders/{order}', [MyOrderWebController::class, 'show'])->name('my-orders.show');
    });

    Route::middleware('role:admin')->group(function () {
        Route::get('/operations', [OperationWebController::class, 'index'])->name('operations.index');
        Route::post('/operations/{order}/status', [OperationWebController::class, 'updateStatus'])->name('operations.status');
    });
});
