<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\InternalUserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::middleware('client.auth')->group(function () {
        Route::post('/auth/login', [AuthController::class, 'login']);
        Route::post('/internal/users', [InternalUserController::class, 'store']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    });
});
