<?php

use Illuminate\Support\Facades\Route;


Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

Route::get('/test', function () {
    return 'Test route working';
});

Route::get('/auth/google', [\App\Http\Controllers\Auth\GoogleDriveController::class, 'redirectToGoogle'])->name('google.auth');
Route::get('/auth/google/callback', [\App\Http\Controllers\Auth\GoogleDriveController::class, 'handleGoogleCallback'])->name('google.auth.callback');

require __DIR__.'/auth.php';
