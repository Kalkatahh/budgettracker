<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::post('/register', [\App\Http\Controllers\Auth\RegisteredUserController::class, 'store']);
    Route::post('/login', [\App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [\App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'destroy']);
    Route::post('/receipts', [\App\Http\Controllers\ReceiptController::class, 'store']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});