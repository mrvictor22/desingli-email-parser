<?php

use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SesEventController;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/transform', [SesEventController::class, 'transform']);
