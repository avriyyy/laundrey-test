<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PromoController;
use App\Http\Controllers\Api\V1\ServiceController;
use App\Http\Controllers\Api\V1\TrackController;
use Illuminate\Support\Facades\Route;

Route::get('/status', function () {
    return response()->json([
        'sukses' => true,
        'pesan' => 'API Laundrey aktif',
        'waktu' => now()->toIso8601String(),
    ]);
});

Route::prefix('v1')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/track/{invoice_number}', [TrackController::class, 'publicTrack']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/services', [ServiceController::class, 'index']);
        Route::get('/services/{service}', [ServiceController::class, 'show']);
        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{order}', [OrderController::class, 'show']);

        Route::middleware('role:tenant')->group(function () {
            Route::post('/services', [ServiceController::class, 'store']);
            Route::put('/services/{service}', [ServiceController::class, 'update']);
            Route::delete('/services/{service}', [ServiceController::class, 'destroy']);
            Route::get('/promos', [PromoController::class, 'index']);
            Route::post('/promos', [PromoController::class, 'store']);
            Route::get('/promos/{promo}', [PromoController::class, 'show']);
            Route::delete('/promos/{promo}', [PromoController::class, 'destroy']);
            Route::post('/orders', [OrderController::class, 'store']);
            Route::put('/orders/{order}', [OrderController::class, 'update']);
        });

        Route::middleware('role:tenant')->group(function () {
            Route::post('/orders/{order}/tracks', [TrackController::class, 'store']);
        });
    });
});
