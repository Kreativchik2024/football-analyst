<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MLController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// ML маршруты (без префикса /api, он добавляется автоматически)
Route::post('/ml/store-prediction', [MLController::class, 'storePrediction']);
Route::get('/ml/status', [MLController::class, 'status']);
Route::get('/ml/training-data', [MLController::class, 'getTrainingData']);
Route::post('/ml/features', [MLController::class, 'getFeatures']);
Route::post('/ml/upcoming-features', [MLController::class, 'getUpcomingFeatures']);