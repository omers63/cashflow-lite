<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ReconciliationController;
use App\Http\Controllers\Api\UserBalanceController;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Reconciliation API
Route::prefix('reconciliation')->group(function () {
    Route::get('/latest', [ReconciliationController::class, 'latest']);
    Route::post('/run', [ReconciliationController::class, 'run']);
    Route::get('/summary', [ReconciliationController::class, 'summary']);
});

// User Balance API
Route::prefix('users')->group(function () {
    Route::get('/{user}/balance', [UserBalanceController::class, 'balance']);
    Route::get('/{user}/available-to-borrow', [UserBalanceController::class, 'availableToBorrow']);
});

// Health check
Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});
