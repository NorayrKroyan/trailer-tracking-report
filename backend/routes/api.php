<?php

use App\Http\Controllers\Api\TrailerTrackingReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('reports/trailer-tracking')->group(function () {
    Route::get('/owners', [TrailerTrackingReportController::class, 'owners']);
    Route::get('/', [TrailerTrackingReportController::class, 'index']);

    Route::get('/vehicle/{vehicleId}', [TrailerTrackingReportController::class, 'vehicleEditor']);
    Route::put('/vehicle/{vehicleId}', [TrailerTrackingReportController::class, 'updateVehicle']);
    Route::delete('/vehicle/{vehicleId}', [TrailerTrackingReportController::class, 'deleteVehicle']);

    Route::get('/driver/{contactId}', [TrailerTrackingReportController::class, 'driverEditor']);
    Route::put('/driver/{contactId}', [TrailerTrackingReportController::class, 'updateDriver']);
});
