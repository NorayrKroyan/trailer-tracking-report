<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TrailerTrackingReportController;

Route::get('/reports/trailer-tracking/owners', [TrailerTrackingReportController::class, 'owners']);
Route::get('/reports/trailer-tracking', [TrailerTrackingReportController::class, 'index']);
