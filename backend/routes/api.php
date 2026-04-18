<?php

use App\Http\Controllers\Api\AnalyzeController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ReviewController;
use Illuminate\Support\Facades\Route;

// Public
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

// Protected
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

    Route::post('/analyze', AnalyzeController::class);

    Route::apiResource('reviews', ReviewController::class);

    Route::get('/dashboard/stats',          [DashboardController::class, 'stats']);
    Route::get('/dashboard/recent-reviews', [DashboardController::class, 'recentReviews']);
    Route::get('/dashboard/topics',         [DashboardController::class, 'topics']);
});
