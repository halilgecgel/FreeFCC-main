<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\TelemetryController;
use App\Http\Controllers\Api\UpdateController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1');

    Route::get('/check-update', [UpdateController::class, 'check']);
    Route::get('/download/{release}', [UpdateController::class, 'download']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/heartbeat', [AuthController::class, 'heartbeat']);
        Route::post('/offline', [AuthController::class, 'offline']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/notifications', [NotificationController::class, 'index']);

        Route::prefix('/telemetry')->group(function () {
            Route::post('/device', [TelemetryController::class, 'deviceTelemetry']);
            Route::post('/fcc-session', [TelemetryController::class, 'fccSession']);
            Route::post('/feature-usage', [TelemetryController::class, 'featureUsage']);
            Route::post('/feature-usage/batch', [TelemetryController::class, 'featureUsageBatch']);
            Route::post('/error', [TelemetryController::class, 'errorLog']);
            Route::post('/error/batch', [TelemetryController::class, 'errorLogBatch']);
            Route::post('/activity', [TelemetryController::class, 'activityLog']);
            Route::post('/activity/batch', [TelemetryController::class, 'activityLogBatch']);
            Route::post('/connection-metrics', [TelemetryController::class, 'connectionMetrics']);
        });
    });
});
