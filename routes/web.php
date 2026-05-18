<?php

use App\Http\Controllers\MetricsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/metrics', [MetricsController::class, 'prometheus']);
Route::get('/metrics/json', [MetricsController::class, 'json']);
