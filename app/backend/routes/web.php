<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FileUploadController;

Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '.*');


Route::post('/api/upload', [FileUploadController::class, 'upload']);
 