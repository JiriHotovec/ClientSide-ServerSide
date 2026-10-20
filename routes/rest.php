<?php

use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\MeasurementController;
use App\Http\Controllers\Api\MeasurementTypeController;
use App\Http\Controllers\Api\PublicDeviceController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:rest')->group(function () {
    // Public (guests allowed) — only public devices, private ones answer 404.
    Route::get('public/devices', [PublicDeviceController::class, 'index']);
    Route::get('public/devices/{id}', [PublicDeviceController::class, 'show']);
    Route::get('public/devices/{id}/measurements', [PublicDeviceController::class, 'measurements']);

    // Authenticated (owner or admin, enforced by policies).
    Route::middleware('auth')->group(function () {
        Route::apiResource('devices', DeviceController::class)->names('api.devices');
        Route::patch('devices/{device}/visibility', [DeviceController::class, 'visibility']);
        Route::post('devices/{device}/token', [DeviceController::class, 'token']);
        Route::get('devices/{device}/measurements', MeasurementController::class);

        Route::get('measurement-types', [MeasurementTypeController::class, 'index']);
        Route::post('measurement-types', [MeasurementTypeController::class, 'store']);
        Route::put('measurement-types/{measurementType}', [MeasurementTypeController::class, 'update']);
        Route::delete('measurement-types/{measurementType}', [MeasurementTypeController::class, 'destroy']);
    });
});
