<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ParseRunsController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\ReviewsController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'login']); # без регистрации

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    Route::post('/settings', [SettingsController::class, 'store']);
    Route::get('/settings', [SettingsController::class, 'show']);
    Route::get('/reviews', [ReviewsController::class, 'index']);
    Route::get('/organizations', [ReviewsController::class, 'organizations']);
    Route::get('/parse-runs', [ParseRunsController::class, 'index']);
    Route::get('/parse-runs/{id}', [ParseRunsController::class, 'show']);
});
